<?php

namespace App\Services;

use App\Exceptions\MissingCheckoutException;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\User;
use App\Notifications\StatusNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Razorpay\Api\Api;

/**
 * Creates the order for a Razorpay payment exactly once, whichever arrives first:
 * the customer's browser after checkout, the Razorpay webhook, or an admin retry.
 */
class PaidOrderService
{
    public function __construct(private OrderService $orders)
    {
    }

    /**
     * @return array{0:Order,1:bool} [order, created now]
     */
    public function complete(string $razorpayOrderId, ?string $paymentId, ?array $data, string $source): array
    {
        try {
            return DB::transaction(function () use ($razorpayOrderId, $paymentId, $data, $source) {
                $pending = PendingCheckout::where('razorpay_order_id', $razorpayOrderId)->lockForUpdate()->first();

                $existing = Order::where('razorpay_order_id', $razorpayOrderId)->first();
                if ($existing) {
                    $this->markCompleted($pending, $existing, $paymentId);

                    return [$existing, false];
                }

                $data = $data ?? $pending?->payload;
                if (!$data) {
                    throw new MissingCheckoutException("No checkout data saved for Razorpay order {$razorpayOrderId}");
                }

                $paymentId = $paymentId ?: ($data['payment_id'] ?? null) ?: $pending?->razorpay_payment_id;

                $order = $this->orders->createOrder(array_merge($data, [
                    'customer_id' => $pending?->customer_id ?? ($data['customer_id'] ?? null),
                    'payment_method' => 'online',
                    'payment_status' => 'paid',
                    'payment_id' => $paymentId,
                    'razorpay_order_id' => $razorpayOrderId,
                ]));

                $this->markCompleted($pending, $order, $paymentId);

                Log::info('Paid order created', [
                    'order_id' => $order->id,
                    'razorpay_order_id' => $razorpayOrderId,
                    'source' => $source,
                ]);

                return [$order, true];
            });
        } catch (QueryException $e) {
            // Unique index on orders.razorpay_order_id: another request created it first.
            $existing = Order::where('razorpay_order_id', $razorpayOrderId)->first();
            if ($existing && ($e->errorInfo[1] ?? null) === 1062) {
                return [$existing, false];
            }

            throw $e;
        }
    }

    /**
     * Confirm with Razorpay that this order was really paid before trusting a browser's
     * "paid" claim: checkout signature when the browser sends it, otherwise ask Razorpay.
     */
    public function isPaid(string $razorpayOrderId, ?string $paymentId, ?string $signature): bool
    {
        $api = new Api(config('services.razorpay.key_id'), config('services.razorpay.key_secret'));

        try {
            if ($paymentId && $signature) {
                $api->utility->verifyPaymentSignature([
                    'razorpay_order_id' => $razorpayOrderId,
                    'razorpay_payment_id' => $paymentId,
                    'razorpay_signature' => $signature,
                ]);

                return true;
            }

            return ($api->order->fetch($razorpayOrderId)['status'] ?? null) === 'paid';
        } catch (\Throwable $e) {
            Log::warning('Razorpay payment check failed', [
                'razorpay_order_id' => $razorpayOrderId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function markFailed(string $razorpayOrderId, string $error): void
    {
        $pending = PendingCheckout::where('razorpay_order_id', $razorpayOrderId)->first();
        if (!$pending || $pending->order_id) {
            return;
        }

        $pending->update([
            'status' => 'failed',
            'error' => mb_substr($error, 0, 1000),
            'paid_at' => $pending->paid_at ?: now(),
        ]);
        $this->alertAdmin("Payment received but order failed (Razorpay {$razorpayOrderId}): {$error}");
    }

    public function alertAdmin(string $title): void
    {
        try {
            $admin = User::where('role', 'admin')->first();
            if ($admin) {
                Notification::send($admin, new StatusNotification([
                    'title' => mb_substr($title, 0, 250),
                    'actionURL' => route('pending-payments.index'),
                    'fas' => 'fa-exclamation-triangle',
                ]));
            }
        } catch (\Throwable $e) {
            Log::error('Paid order admin alert failed', ['message' => $e->getMessage()]);
        }
    }

    private function markCompleted(?PendingCheckout $pending, Order $order, ?string $paymentId): void
    {
        if (!$pending || $pending->order_id) {
            return;
        }

        $pending->update([
            'status' => 'completed',
            'order_id' => $order->id,
            'razorpay_payment_id' => $pending->razorpay_payment_id ?: $paymentId,
            'paid_at' => $pending->paid_at ?: now(),
            'error' => null,
        ]);
    }
}
