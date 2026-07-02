<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Order;
use App\Services\ShiprocketService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Customer's own order list. Scoped to the authenticated customer only —
     * never trust a customer_id passed in from the client for this.
     */
    public function index(Request $request)
    {
        $customerId = Auth::guard('customer')->id();

        if (!$customerId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $orders = Order::with([
                'items',
                'returnRequest',
                'returnRequest.orderItem',
                'returnRequest.replacementOrder.items',
            ])
            ->where('customer_id', $customerId)
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function store(Request $request, OrderService $orderService)
    {
        try {
            $order = $orderService->createOrder($request->all());

            try {
                Mail::send([], [], function ($message) use ($order, $request) {
                    $html = '
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="UTF-8">
            <title>Order Confirmation</title>
            </head>
            <body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;">

            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:40px 0;">
            <tr>
            <td align="center">

            <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">

            <!-- Header -->
            <tr>
            <td style="background:#000;padding:20px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:20px;">
            DHIRAGO 
            </h1>
            </td>
            </tr>

            <!-- Content -->
            <tr>
            <td style="padding:40px;">

            <h2 style="margin-top:0;color:#111;">
            Order Confirmed 🎉
            </h2>

            <p style="font-size:16px;color:#555;line-height:26px;">
            Hi ' . $request->name . ',
            </p>

            <p style="font-size:16px;color:#555;line-height:26px;">
            Thank you for your order. Your order has been placed successfully.
            </p>

            <!-- Order Box -->
            <table width="100%" cellpadding="0" cellspacing="0"
            style="margin:30px 0;background:#fafafa;border:1px solid #eee;border-radius:10px;">

            <tr>
            <td style="padding:20px;">

            <p style="margin:0 0 10px;">
            <strong>Order Number:</strong>
            #' . $order->order_number . '
            </p>

            <p style="margin:0 0 10px;">
            <strong>Payment Method:</strong>
            ' . $order->payment_method . '
            </p>

            <p style="margin:0 0 10px;">
            <strong>Payment Status:</strong>
            ' . $order->payment_status . '
            </p>

            <p style="margin:0;">
            <strong>Total Amount:</strong>
            ₹' . $order->total_amount . '
            </p>

            </td>
            </tr>

            </table>

            <!-- Shipping Address -->
            <h3 style="color:#111;">
            Shipping Address
            </h3>

            <p style="font-size:15px;color:#666;line-height:24px;">
            ' . $order->first_name . '<br>
            ' . $order->address1 . ' ' . $order->address2 . '<br>
            ' . $order->city . ', ' . $order->state . '<br>
            ' . $order->post_code . '<br>
            Phone: ' . $order->phone . '
            </p>

            <!-- Button -->
            <div style="text-align:center;margin:40px 0;">
            <a href="https://dhirago.com/return/track-order"
            style="background:#000;color:#fff;text-decoration:none;
            padding:14px 30px;border-radius:6px;font-size:16px;
            display:inline-block;">
            Track Your Order
            </a>
            </div>

            <p style="font-size:15px;color:#666;line-height:24px;">
            We’ll notify you once your order is shipped.
            </p>

            <p style="font-size:15px;color:#666;">
            Thank you for shopping with us ❤️
            </p>

            </td>
            </tr>

            <!-- Footer -->
            <tr>
            <td style="background:#fafafa;padding:20px;text-align:center;
            font-size:13px;color:#999;">
            © ' . date('Y') . ' DHIRAGO. All rights reserved.
            </td>
            </tr>

            </table>

            </td>
            </tr>
            </table>

            </body>
            </html>
            '; // unchanged HTML

                    $message->to($request->email)
                        ->subject('Your Order Has Been Placed Successfully')
                        ->html($html);
                });
            } catch (\Exception $mailError) {
                Log::error('Mail Error', ['message' => $mailError->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'order'   => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Order Error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function latest(Request $request)
    {
        $order = Order::latest()->first();

        if (!$order) {
            return response()->json(['message' => 'No order found'], 404);
        }

        return response()->json(['order' => $order]);
    }

    /**
     * Called by your Shiprocket webhook (or an admin action) when the forward
     * shipment is actually delivered. This is what the 7-day return window
     * should be measured from — not the estimated delivery date.
     */
    public function markDelivered(Request $request, $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $order->update([
            'status'       => 'delivered',
            'delivered_at' => $request->input('delivered_at', now()),
        ]);

        return response()->json(['success' => true, 'order' => $order]);
    }
}