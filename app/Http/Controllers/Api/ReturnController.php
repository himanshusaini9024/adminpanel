<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ReturnOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ShiprocketService;
use App\Services\OrderService;
use Razorpay\Api\Api;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnController extends Controller
{
    /**
     * CUSTOMER: create a return or exchange request.
     */
    public function create(Request $request)
    {

        
        $customerId = Auth::guard('customer')->id();

        if (!$customerId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'order_id'      => 'required',        // this is actually the order_number from the frontend
            'order_item_id' => 'required|exists:order_items,id',
            'reason'        => 'required|string',
            'comment'       => 'required|string',
            'type'          => 'required|in:return,exchange',
            'exchange_size' => 'required_if:type,exchange',
        ]);

        $order = Order::where('order_number', $request->order_id)
            ->where('customer_id', $customerId) 
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // Make sure the item being returned actually belongs to this order.
        $orderItem = OrderItem::where('id', $request->order_item_id)
            ->where('order_id', $order->id)
            ->first();

        if (!$orderItem) {
            return response()->json([
                'message' => 'Item does not belong to this order',
            ], 422);
        }

        // Allow only delivered orders, and only within the return window.
        if (!$order->isWithinReturnWindow()) {
            return response()->json([
                'message' => 'Return/exchange window has closed for this order',
            ], 400);
        }

        $existing = ReturnOrder::where('order_id', $order->id)->first();

        if ($existing) {
            return response()->json([
                'message' => 'A return/exchange request already exists for this order',
            ], 400);
        }

        $return = DB::transaction(function () use ($order, $orderItem, $request, $customerId) {
            return ReturnOrder::create([
                'order_id'      => $order->id,
                'order_item_id' => $orderItem->id,
                'order_number'  => $order->order_number,
                'user_id'       => $customerId,
                'reason'        => $request->reason,
                'type'          => $request->type,
                'exchange_size' => $request->exchange_size,
                'comment'       => $request->comment,
                'status'        => 'pending',
            ]);
        });

        return response()->json([
            'success' => true,
            'return'  => $return,
        ]);
    }

    // ============================================================
    // ADMIN
    // ============================================================

    public function adminReturns()
    {
        $returns = ReturnOrder::with('order', 'orderItem')
            ->latest()
            ->get();

        return view('backend.return.index', compact('returns'));
    }

    /**
     * ADMIN: approve a pending return/exchange and schedule reverse pickup via Shiprocket.
     */
    public function approve($id, $sku)
    {
        $return = ReturnOrder::with('order')->findOrFail($id);

        if ($return->status !== 'pending') {
            return back()->with('error', 'Already processed');
        }

        $shiprocket = new ShiprocketService();
        $response = $shiprocket->createReturn($return, $sku);

        if (isset($response['status_code']) && in_array($response['status_code'], [21, 22, 23])) {
            $return->update([
                'status'              => 'pickup_scheduled',
                'reverse_order_id'    => $response['order_id'] ?? null,
                'reverse_shipment_id' => $response['shipment_id'] ?? null,
                'courier'             => $response['company_name'] ?? null,
            ]);

            return back()->with('success', 'Reverse pickup scheduled successfully');
        }

        $return->update(['status' => 'pickup_failed']);

        return back()->with('error', 'Failed to schedule reverse pickup with courier');
    }

    /**
     * ADMIN: reject a pending return/exchange.
     */
    public function reject($id)
    {
        $return = ReturnOrder::findOrFail($id);

        if ($return->status !== 'pending') {
            return back()->with('error', 'Already processed');
        }

        $return->update(['status' => 'rejected']);

        return back()->with('success', 'Return rejected successfully');
    }

    /**
     * WEBHOOK / ADMIN: mark the reverse shipment as picked up from the customer.
     */
    public function markPickedUp($id)
    {
        $return = ReturnOrder::findOrFail($id);

        if ($return->status !== 'pickup_scheduled') {
            return back()->with('error', 'Return is not awaiting pickup');
        }

        $return->update(['status' => 'picked_up']);

        return back()->with('success', 'Marked as picked up');
    }

    /**
     * WEBHOOK / ADMIN: mark the returned item as received at the warehouse.
     * This unlocks refund / exchange processing below.
     */
    public function markDelivered($id)
    {
        $return = ReturnOrder::findOrFail($id);

        if ($return->status !== 'picked_up') {
            return back()->with('error', 'Return has not been picked up yet');
        }

        $return->update(['status' => 'delivered']);

        return back()->with('success', 'Marked as received at warehouse');
    }

    /**
     * ADMIN: issue a Razorpay refund for a return once the item is received.
     */
    public function processRefund($id, $paymentId)
    {
        try {
            $return = ReturnOrder::with('order')->findOrFail($id);

            if ($return->type !== 'return') {
                return back()->with('error', 'This request is an exchange, not a return.');
            }

            if ($return->status !== 'delivered') {
                return back()->with('error', 'Product not received yet');
            }

            $api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

            $refund = $api->payment
                ->fetch($paymentId)
                ->refund(['amount' => $return->order->total_amount * 100]);

            $return->update([
                'status'         => 'refunded',
                'refund_id'      => $refund['id'],
                'refund_amount'  => $return->order->total_amount,
                'refunded_at'    => now(),
            ]);

            $return->order->update(['status' => 'refunded']);

            return back()->with('success', 'Refund processed successfully');
        } catch (\Exception $e) {
            Log::error('Refund Error', ['message' => $e->getMessage()]);

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * ADMIN: create the replacement order for an exchange once the original
     * item is received back at the warehouse. Only the exchanged line item's
     * size/color changes — every other item on the order ships unchanged.
     */
    public function processExchange($id, OrderService $orderService)
    {
        $return = ReturnOrder::with('order.items', 'orderItem')->findOrFail($id);

        if ($return->type !== 'exchange') {
            return back()->with('error', 'This is not an exchange request.');
        }

        if ($return->status !== 'delivered') {
            return back()->with('error', 'Returned product has not been received yet.');
        }

        if ($return->replacement_order_id) {
            return back()->with('error', 'Replacement order already created.');
        }

        try {
            $items = [];

            foreach ($return->order->items as $item) {
                $isExchangedItem = $item->id === $return->order_item_id;

                $items[] = [
                    'id'       => $item->product_id,
                    'sku'      => $item->sku,
                    'price'    => $item->price,
                    'quantity' => $item->quantity,
                    'thumb'    => ['url' => $item->image],
                    // Only the exchanged item's size changes; everything else
                    // carries over exactly as it was on the original order.
                    'size'     => $isExchangedItem ? $return->exchange_size : $item->size,
                    'name'     => $item->name,
                    'color'    => $isExchangedItem
                        ? ($return->exchange_color ?? $item->color)
                        : $item->color,
                ];
            }

            $data = [
                'customer_id'       => $return->order->customer_id,
                'payment_id'        => null,
                'razorpay_order_id' => null,

                'sub_total'         => $return->order->sub_total,
                'total_amount'      => $return->order->total_amount,
                'quantity'          => $return->order->quantity,

                'city'              => $return->order->city,
                'state'             => $return->order->state,
                'pincode'           => $return->order->post_code,

                'payment_method'    => $return->order->payment_method,
                'payment_status'    => 'paid', // already paid on the original order

                'first_name'        => $return->order->first_name,
                'last_name'         => $return->order->last_name,

                'phone'             => $return->order->phone,
                'email'             => $return->order->email,

                'address1'          => $return->order->address1,
                'address2'          => $return->order->address2,

                'items'             => $items,
            ];

            // OrderService manages its own transaction internally.
            $replacementOrder = $orderService->createOrder($data);

            $replacementOrder->update([
                'parent_order_id' => $return->order->id,
                'order_type'      => 'exchange',
            ]);

            $return->order->update(['status' => 'exchanged']);

            $return->update([
                'replacement_order_id' => $replacementOrder->id,
                'status'                => 'replacement_created',
            ]);

            Log::info('Exchange replacement order created', [
                'return_id'            => $return->id,
                'replacement_order_id' => $replacementOrder->id,
            ]);

            return back()->with('success', 'Replacement order created successfully.');
        } catch (\Exception $e) {
            Log::error('Exchange Error', ['message' => $e->getMessage()]);

            return back()->with('error', $e->getMessage());
        }
    }
}