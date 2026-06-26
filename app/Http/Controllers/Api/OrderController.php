<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Order;
use App\Services\ShiprocketService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    //

    public function index(Request $request)
    {
        // $customerId = $request->customer_id;
        $customerId = Auth::guard('customer')->id();
        $orders = Order::with('items',  'returnRequest')
            ->where('customer_id', $customerId)
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function store(Request $request, ShiprocketService $shiprocket)
    {
        DB::beginTransaction();

        try {

            Log::info('Order Data', $request->all());

            // Create Order
            $order = Order::create([
                'customer_id'          => $request->customer_id,

                'razorpay_payment_id'  => $request->payment_id,
                'razorpay_order_id'    => $request->razorpay_order_id,

                'sub_total'            => $request->sub_total,
                'total_amount'         => $request->total_amount,
                'quantity'             => $request->quantity,

                'city'                 => $request->city,

                'payment_method'       => $request->payment_method,
                'payment_status'       => $request->payment_status,

                'status'               => 'new',

                'first_name'           => $request->name,
                'last_name'            => $request->name,

                'phone'                => $request->phone,

                'address1'             => $request->address1,
                'address2'             => $request->address2,

                'state'                => $request->state,
                'country'              => 'IND',

                'email'                => $request->email,
                'post_code'            => $request->pincode,
            ]);

            // Safe Order Number
            $order->order_number = 100 + $order->id;
            $order->save();

            $shiprocketItems = [];

            foreach ($request->items as $item) {

                OrderItem::create([
                    'order_id'  => $order->id, // FIXED
                    'order_number' => $order->order_number,


                    'product_id' => $item['id'],
                    'sku'        => $item['sku'],

                    'image'      => $item['thumb']['url'] ?? null,

                    'price'      => $item['price'],
                    'quantity'   => $item['quantity'],

                    'size'       => $item['size'] ?? null,
                    'color'      => $item['color'] ?? null,
                ]);

                $shiprocketItems[] = [
                    "name"          => $item['name'],
                    "sku"           => $item['sku'],
                    "units"         => $item['quantity'],
                    "selling_price" => $item['price'],
                ];
            }

            // Shiprocket
            $allowshipment = env('SHIPMENT_LIVE', false);

            if ($allowshipment) {

                $shiprocketResponse = $shiprocket->createOrder(
                    $order,
                    $shiprocketItems
                );

                Log::info('Shiprocket Response', [
                    'response' => $shiprocketResponse
                ]);

                if (isset($shiprocketResponse['shipment_id'])) {

                    $order->shipment_id = $shiprocketResponse['shipment_id'];

                    $order->awb_code =
                        $shiprocketResponse['awb_code'] ?? null;

                    $order->save();
                }
            } else {

                Log::info('Shiprocket disabled');

                $shiprocketResponse = [
                    'testing' => true,
                    'message' => 'Shipment skipped in testing mode'
                ];
            }

            DB::commit();

            // Send Mail AFTER successful commit
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
            ';

                    $message->to($request->email)
                        ->subject('Your Order Has Been Placed Successfully')
                        ->html($html);
                });
            } catch (\Exception $mailError) {

                Log::error('Mail Error', [
                    'message' => $mailError->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'order' => $order,
                'shiprocket' => $shiprocketResponse,
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

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
            return response()->json([
                'message' => 'No order found'
            ], 404);
        }

        return response()->json([
            'order' => $order
        ]);
    }
}
