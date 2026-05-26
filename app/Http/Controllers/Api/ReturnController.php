<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ReturnOrder;
use App\Models\Order;
use App\Services\ShiprocketService;

class ReturnController extends Controller
{
    //
    public function create(Request $request)
    {
        $request->validate([
            'order_id' => 'required',
            'reason' => 'required'
        ]);

        $order = Order::where(
            'order_number',
            $request->order_id
        )->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // allow only delivered orders
        if ($order->status !== 'delivered') {
            return response()->json([
                'message' => 'Return only allowed after delivery'
            ], 400);
        }

        $return = ReturnOrder::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'reason' => $request->reason,
            'comment' => $request->comment,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'return' => $return
        ]);
    }

    // ADMIN RETURNS LIST
    public function adminReturns()
    {
        $returns = ReturnOrder::with('order','items')
            ->latest()
            ->get();


        return view('backend.return.index', compact('returns'));
    }

    // APPROVE RETURN
    public function approve($id,$sku)
    {
        $return = ReturnOrder::with('order')
            ->findOrFail($id);


        // already processed
        if ($return->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Already processed'
            ]);
        }

        $shiprocket = new ShiprocketService();

        $response = $shiprocket->createReturn($return,$sku);
       


        if (
            isset($response['status_code']) &&
            in_array($response['status_code'], [21, 22, 23])
        ) {

            $return->update([

                'status' => 'pickup_scheduled',

                'reverse_order_id' =>
                $response['order_id'] ?? null,

                'reverse_shipment_id' =>
                $response['shipment_id'] ?? null,

                'courier' =>
                $response['company_name'] ?? null,
            ]);

            return redirect()
                ->back()
                ->with(
                    'success',
                    'Reverse pickup scheduled successfully'
                );
        }

        // FAILED
        $return->update([
            'status' => 'pickup_failed'
        ]);

        // $return->update([
        //     'status' => 'pickup_scheduled',
        //     'reverse_awb' => $response['awb_code'] ?? null,
        //     'courier' => $response['courier_name'] ?? null,
        // ]);

        return redirect()
            ->back()
            ->with('success', 'Return approved successfully');
    }

    // REJECT RETURN
    public function reject($id)
    {
        $return = ReturnOrder::findOrFail($id);

        $return->update([
            'status' => 'rejected'
        ]);

        return redirect()
            ->back()
            ->with('success', 'Return Rejet successfully');
    }
}
