<?php

namespace App\Http\Controllers\Api;

use Razorpay\Api\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Razorpay\Api\Errors\SignatureVerificationError;

class PaymentController extends Controller
{
    //
    public function createRazorpayOrder(Request $request)
    {
        $api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

        $order = $api->order->create([
            'receipt' => 'ORD_' . time(),
            'amount' => $request->amount * 100,
            'currency' => 'INR'
        ]);

        // ✅ IMPORTANT: return only needed fields
        return response()->json([
            'id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency']
        ]);
    }


    public function verifyPayment(Request $request)
    {
        \Log::info("VERIFY DATA:", $request->all());

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
