<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Order;

class OrderController extends Controller
{
    //

    public function index()
    {
        $orders = Order::with('items')
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $lastOrder = Order::latest()->first();
        $nextId = $lastOrder ? $lastOrder->id + 1 : 1;

        $orderNumber = 100 + $nextId; // start from 101

        $order = Order::create([
            'order_number' => $orderNumber, // ✅ FIX HERE

            'user_id' => auth()->id() ?? 1,
            'sub_total' => $request->sub_total,
            'total_amount' => $request->total_amount,
            'quantity' => $request->quantity,
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
            'email' => 'test@gmail.com',
            'post_code' => $request->pincode,
        ]);

        foreach ($request->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'price' => $item['price'],
                'quantity' => $item['count'],
                'size' => $item['size'] ?? null,
                'color' => $item['color'] ?? null,
            ]);
        }

        return response()->json($order);
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
