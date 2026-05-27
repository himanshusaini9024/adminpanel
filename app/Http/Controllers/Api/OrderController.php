<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Order;
use App\Services\ShiprocketService;

class OrderController extends Controller
{
    //

    public function index(Request $request)
    {
        $customerId = $request->customer_id;
        $orders = Order::with('items',  'returnRequest')
            ->where('customer_id', $customerId)
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function store(Request $request, ShiprocketService $shiprocket)
    {


        $lastOrder = Order::latest()->first();
        $nextId = $lastOrder ? $lastOrder->id + 1 : 1;

        $orderNumber = 100 + $nextId;
        // Create order first
        $order = Order::create([

            'order_number' => $orderNumber, // ✅ FIX HERE

            'customer_id' => $request->customer_id,
            'payment_id' => $request->payment_id,

            'sub_total' => $request->sub_total,
            'total_amount' => $request->total_amount,
            'quantity' => $request->quantity,
            'city' => $request->city,

            'payment_method' => $request->payment_method,
            'payment_status' => $request->payment_status,

            'status' => 'new',

            'first_name' => $request->name,
            'last_name' => $request->name,

            'phone' => $request->phone,

            'address1' => $request->address1,
            'address2' => $request->address2,

            'state' => $request->state,
            'country' => 'IND',

            'email' => $request->email,
            'post_code' => $request->pincode,
        ]);

        // safer order number
        $order->save();

        $shiprocketItems = [];

        foreach ($request->items as $item) {

            // save order items only ONCE
            OrderItem::create([
                'order_id' => $order->id,

                'product_id' => $item['id'],
                'sku' => $item['sku'],

                'image' => $item['thumb']['url'] ?? null,

                'price' => $item['price'],
                'quantity' => $item['quantity'],

                'size' => $item['size'] ?? null,
                'color' => $item['color'] ?? null,
            ]);

            // shiprocket items
            $shiprocketItems[] = [
                "name" => $item['name'],
                "sku" =>  $item['sku'],
                "units" => $item['quantity'],
                "selling_price" => $item['price'],
            ];
        }

        // Shiprocket API
        try {

            // $shiprocketResponse = $shiprocket->createOrder(
            //     $order,
            //     $shiprocketItems
            // );

            // if (isset($shiprocketResponse['shipment_id'])) {

            //     $order->shipment_id =
            //         $shiprocketResponse['shipment_id'];

            //     $order->awb_code =
            //         $shiprocketResponse['awb_code'] ?? null;

            //     $order->save();
            // }
            if (app()->environment('production')) {
                $shiprocketResponse = $shiprocket->createOrder(
                    $order,
                    $shiprocketItems
                );

                // save shipment details
                if (isset($shiprocketResponse['shipment_id'])) {

                    $order->shipment_id =
                        $shiprocketResponse['shipment_id'];

                    $order->awb_code =
                        $shiprocketResponse['awb_code'] ?? null;

                    $order->save();
                }
            } else {

                $shiprocketResponse = [
                    'testing' => true,
                    'message' => 'Shipment skipped in local/testing environment'
                ];
            }


        } catch (\Exception $e) {

            $shiprocketResponse = [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }

        return response()->json([
            'success' => true,
            'order' => $order,
            'shiprocket' => $shiprocketResponse,
        ]);
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
