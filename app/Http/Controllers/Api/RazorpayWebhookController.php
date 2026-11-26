<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MissingCheckoutException;
use App\Http\Controllers\Controller;
use App\Jobs\MetaPurchaseJob;
use App\Jobs\OrderPlacedJob;
use App\Models\PendingCheckout;
use App\Services\PaidOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

/**
 * Razorpay calls this server-to-server when a payment succeeds, so the order is created even
 * if the customer's browser never comes back (UPI app switch, tab closed, network drop).
 * Configure in Razorpay Dashboard → Webhooks with events order.paid and payment.captured.
 */
class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, PaidOrderService $paidOrders)
    {
        $secret = config('services.razorpay.webhook_secret');
        if (!$secret) {
            Log::error('Razorpay webhook received but RAZORPAY_WEBHOOK_SECRET is not set');

            return response()->json(['message' => 'Webhook not configured'], 500);
        }

        $body = $request->getContent();
        try {
            (new Api(config('services.razorpay.key_id'), config('services.razorpay.key_secret')))
                ->utility->verifyWebhookSignature($body, (string) $request->header('X-Razorpay-Signature'), $secret);
        } catch (\Throwable $e) {
            Log::warning('Razorpay webhook signature invalid', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $payment = $request->input('payload.payment.entity', []);
        $razorpayOrderId = $request->input('payload.order.entity.id') ?: ($payment['order_id'] ?? null);
        $paymentId = $payment['id'] ?? null;

        Log::info('Razorpay webhook', [
            'event' => $event,
            'razorpay_order_id' => $razorpayOrderId,
            'payment_id' => $paymentId,
            'amount' => isset($payment['amount']) ? $payment['amount'] / 100 : null,
        ]);

        if (!in_array($event, ['order.paid', 'payment.captured'], true) || !$razorpayOrderId) {
            return response()->json(['status' => 'ignored']);
        }

        PendingCheckout::where('razorpay_order_id', $razorpayOrderId)
            ->whereNull('paid_at')
            ->update(['paid_at' => now(), 'razorpay_payment_id' => $paymentId, 'status' => 'paid']);

        try {
            [$order, $created] = $paidOrders->complete($razorpayOrderId, $paymentId, null, 'razorpay_webhook');

            if ($created) {
                OrderPlacedJob::dispatch($order->id);
                MetaPurchaseJob::dispatchAfterResponse($order->id, [
                    'event_source_url' => rtrim((string) config('services.webpush.storefront_url'), '/') . '/success',
                ]);
                Log::warning('Order created from Razorpay webhook (browser did not save it)', [
                    'order_id' => $order->id,
                    'razorpay_order_id' => $razorpayOrderId,
                ]);
            }

            return response()->json(['status' => 'ok', 'order_id' => $order->id]);
        } catch (\InvalidArgumentException $e) {
            // Bad checkout data: retrying won't help, the admin has to step in.
            $paidOrders->markFailed($razorpayOrderId, $e->getMessage());

            return response()->json(['status' => 'failed']);
        } catch (MissingCheckoutException $e) {
            Log::error('Razorpay payment without saved checkout', [
                'razorpay_order_id' => $razorpayOrderId,
                'payment_id' => $paymentId,
            ]);
            $paidOrders->alertAdmin("Payment {$paymentId} received but no checkout found — check Razorpay and contact the customer");

            return response()->json(['status' => 'no_checkout']);
        } catch (\Throwable $e) {
            // Temporary problem (DB down, etc.): non-2xx makes Razorpay retry.
            Log::error('Razorpay webhook order creation failed', [
                'razorpay_order_id' => $razorpayOrderId,
                'message' => $e->getMessage(),
            ]);
            PendingCheckout::where('razorpay_order_id', $razorpayOrderId)
                ->whereNull('order_id')
                ->update(['error' => mb_substr($e->getMessage(), 0, 1000)]);

            return response()->json(['status' => 'error'], 500);
        }
    }
}
