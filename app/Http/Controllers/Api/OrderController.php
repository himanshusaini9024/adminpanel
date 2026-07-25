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