<?php

namespace App\Http\Controllers;

use App\Jobs\MetaPurchaseJob;
use App\Jobs\OrderPlacedJob;
use App\Models\PendingCheckout;
use App\Services\PaidOrderService;
use Illuminate\Http\Request;

/**
 * Online payments that reached Razorpay but have no order in the shop.
 */
class PendingPaymentController extends Controller
{
    public function index()
    {
        $checkouts = PendingCheckout::needsAttention()->latest('paid_at')->get();

        return view('backend.pending-payments.index', compact('checkouts'));
    }

    /**
     * Create the order from the saved checkout. Works for a listed payment, or for any
     * Razorpay order ID typed in (e.g. the webhook never arrived) once Razorpay confirms it is paid.
     */
    public function retry(Request $request, PaidOrderService $paidOrders)
    {
        $data = $request->validate(['razorpay_order_id' => 'required|string|max:64']);
        $razorpayOrderId = trim($data['razorpay_order_id']);
        $checkout = PendingCheckout::where('razorpay_order_id', $razorpayOrderId)->first();

        if (!$checkout) {
            return back()->with('error', "No saved checkout for {$razorpayOrderId}. Create the order by hand or refund it in Razorpay.");
        }

        if ($checkout->order_id) {
            return back()->with('success', "Already has order #{$checkout->order->order_number}.");
        }

        if (!$checkout->paid_at) {
            if (!$paidOrders->isPaid($razorpayOrderId, null, null)) {
                return back()->with('error', "Razorpay says {$razorpayOrderId} is not paid. No order created.");
            }
            $checkout->update(['paid_at' => now(), 'status' => 'paid']);
        }

        try {
            [$order, $created] = $paidOrders->complete($razorpayOrderId, $checkout->razorpay_payment_id, null, 'admin_retry');
        } catch (\Throwable $e) {
            $checkout->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);

            return back()->with('error', 'Order could not be created: ' . $e->getMessage());
        }

        if ($created) {
            OrderPlacedJob::dispatch($order->id);
            MetaPurchaseJob::dispatchAfterResponse($order->id, [
                'event_source_url' => rtrim((string) config('services.webpush.storefront_url'), '/') . '/success',
            ]);
        }

        return redirect()->route('order.show', $order->id)
            ->with('success', "Order #{$order->order_number} created for this payment.");
    }
}
