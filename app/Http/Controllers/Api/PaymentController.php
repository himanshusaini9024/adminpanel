<?php

namespace App\Http\Controllers\Api;

use Razorpay\Api\Api;
use App\Http\Controllers\Controller;
use App\Models\PendingCheckout;
use App\Services\CouponDiscountService;
use App\Services\FirstOrderDiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function createRazorpayOrder(
        Request $request,
        FirstOrderDiscountService $firstOrderDiscount,
        CouponDiscountService $couponDiscount
    ) {
        $customerId = Auth::guard('customer')->id();

        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|integer|min:0',
            'coupon_code' => 'nullable|string|max:50',
            'checkout' => 'nullable|array',
            'checkout.items' => 'required_with:checkout|array|min:1',
        ]);

        $subTotal = isset($data['items']) && count($data['items'])
            ? $firstOrderDiscount->subtotalFromItems($data['items'])
            : round((float) ($data['amount'] ?? 0), 2);

        $firstQuote = $firstOrderDiscount->quote($subTotal, $customerId);
        $afterFirst = $firstQuote['total'];

        $couponQuote = $couponDiscount->quote(
            $afterFirst,
            $data['coupon_code'] ?? null,
            $customerId
        );

        $couponAmount = $couponQuote['valid'] ? $couponQuote['discount'] : 0.0;
        $payable = round(max(0, $afterFirst - $couponAmount), 2);

        if ($payable <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid order amount',
            ], 422);
        }

        $api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

        $order = $api->order->create([
            'receipt' => 'ORD_' . time(),
            'amount' => (int) round($payable * 100),
            'currency' => 'INR',
            'notes' => [
                'customer_id' => (string) $customerId,
            ],
        ]);

        $checkout = $request->input('checkout');
        if (is_array($checkout) && !empty($checkout['items'])) {
            PendingCheckout::create([
                'razorpay_order_id' => $order['id'],
                'customer_id' => $customerId,
                'amount' => $payable,
                'payload' => array_merge(
                    Arr::except($checkout, ['payment_status', 'payment_id', 'razorpay_order_id', 'razorpay_signature']),
                    ['customer_id' => $customerId]
                ),
            ]);
        }

        return response()->json([
            'id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'quote' => [
                'sub_total' => $subTotal,
                'first_order_discount' => $firstQuote['discount'],
                'coupon' => $couponQuote,
                'total' => $payable,
            ],
        ]);
    }

    public function verifyPayment(Request $request)
    {
        $api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

        try {
            if (
                !$request->razorpay_order_id ||
                !$request->razorpay_payment_id ||
                !$request->razorpay_signature
            ) {
                return response()->json([
                    'status' => false,
                    'message' => 'Missing required fields'
                ], 400);
            }

            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature
            ];

            $api->utility->verifyPaymentSignature($attributes);

            return response()->json([
                'status' => true,
                'message' => 'Payment verified'
            ]);
        } catch (\Exception $e) {
            \Log::error("VERIFY ERROR: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
