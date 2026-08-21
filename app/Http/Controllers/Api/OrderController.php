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
use App\Jobs\OrderPlacedJob;
use App\Jobs\OrderStatusNotificationJob;

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
            OrderPlacedJob::dispatch($order->id);

         
            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'order'   => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Order controller Error', [
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

    /**
     * Public order tracking by order number or AWB / tracking ID.
     * Returns a limited order payload suitable for the track-order UI.
     */
    public function track(Request $request)
    {
        $data = $request->validate([
            'query' => 'required|string|max:100',
        ]);

        $query = trim($data['query']);
        $normalized = ltrim($query, '#');

        $order = Order::with('items')
            ->where(function ($q) use ($normalized) {
                $q->where('order_number', $normalized)
                  ->orWhere('awb_code', $normalized);
            })
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'No order found for this order ID.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'first_name' => $order->first_name,
                'last_name' => $order->last_name,
                'phone' => $order->phone,
                'address1' => $order->address1,
                'address2' => $order->address2,
                'city' => $order->city,
                'state' => $order->state,
                'country' => $order->country,
                'post_code' => $order->post_code,
                'awb_code' => $order->awb_code,
                'courier_name' => $order->courier_name,
                'created_at' => $order->created_at,
                'expected_delivery_date' => $order->expected_delivery_date,
                'delivered_at' => $order->delivered_at,
                'can_update_address' => $order->can_update_address,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'size' => $item->size,
                        'color' => $item->color,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'image' => $item->image,
                    ];
                })->values(),
            ],
        ]);
    }


    /**
     * Customer may update delivery address within 24 hours of placing the order,
     * and only while the order is still new/processing (not yet shipped).
     */
    public function updateAddress(Request $request, $orderNumber)
    {
        $customerId = Auth::guard('customer')->id();

        if (!$customerId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $order = Order::with('items')
            ->where('order_number', ltrim((string) $orderNumber, '#'))
            ->where('customer_id', $customerId)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (!$order->can_update_address) {
            return response()->json([
                'success' => false,
                'message' => 'Address can only be updated within 24 hours of order placement, and before the order is shipped.',
            ], 422);
        }

        $data = $request->validate([
            'first_name' => 'required|string|max:120',
            'phone'      => 'required|string|max:20',
            'address1'   => 'required|string|max:255',
            'address2'   => 'nullable|string|max:255',
            'city'       => 'required|string|max:120',
            'state'      => 'required|string|max:120',
            'post_code'  => 'required|string|max:20',
            'country'    => 'nullable|string|max:60',
        ]);

        $order->update([
            'first_name' => $data['first_name'],
            'last_name'  => $data['first_name'],
            'phone'      => $data['phone'],
            'address1'   => $data['address1'],
            'address2'   => $data['address2'] ?? null,
            'city'       => $data['city'],
            'state'      => $data['state'],
            'post_code'  => $data['post_code'],
            'country'    => $data['country'] ?? ($order->country ?: 'IND'),
        ]);

        $order->load('items');

        return response()->json([
            'success' => true,
            'message' => 'Delivery address updated successfully.',
            'order'   => $order->fresh(['items', 'returnRequest', 'returnRequest.orderItem', 'returnRequest.replacementOrder.items']),
        ]);
    }

    public function markDelivered(Request $request, $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();
        $wasDelivered = $order->status === 'delivered';

        $order->update([
            'status'       => 'delivered',
            'delivered_at' => $request->input('delivered_at', now()),
        ]);

        if (!$wasDelivered) {
            OrderStatusNotificationJob::dispatch($order->id, 'delivered');
        }

        return response()->json(['success' => true, 'order' => $order]);
    }
}