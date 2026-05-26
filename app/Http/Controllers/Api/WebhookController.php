<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\ReturnOrder;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $token = $request->header('Authorization');
        if ($token !== 'dhirago_shiprocket_secure_12345') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }
        \Log::info('Shiprocket Webhook', $request->all());
        $data = $request->all();

        if (isset($data['channel_order_id'])) {

            $return = ReturnOrder::where(
                'reverse_shipment_id',
                $data['shipment_id'] ?? null
            )->first();

            if ($return) {

                $status = strtoupper($data['status'] ?? '');

                if (str_contains($status, 'CANCEL')) {

                    $return->status = 'rejected';
                } elseif (str_contains($status, 'PICKUP')) {

                    $return->status = 'pickup_scheduled';
                } elseif (str_contains($status, 'PICKED')) {

                    $return->status = 'picked_up';
                } elseif (str_contains($status, 'TRANSIT')) {

                    $return->status = 'in_transit';
                } elseif (str_contains($status, 'DELIVERED')) {

                    $return->status = 'delivered';
                }

                $return->courier =
                    $data['company_name'] ?? null;

                $return->save();

                \Log::info('RETURN UPDATED', [
                    'return_id' => $return->id,
                    'status' => $return->status
                ]);
            }

            return response()->json([
                'success' => true
            ]);
        }


        $awb = $data['awb'] ?? null;
        $status = $data['shipment_status'] ?? null;
        $courier = $data['courier_name'] ?? null;
        $orderId = $data['order_id'] ?? null;
        // remove DHI-
        $orderNumber = str_replace('DHI-', '', $orderId);
        $order = Order::where('order_number', $orderNumber)->first();
        if (!$order && $awb) {
            $order = Order::where('awb_code', $awb)->first();
        }
        if (!$order) {
            \Log::error('Order not found', [
                'order_number' => $orderNumber,
                'awb' => $awb
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ]);
        }
        if ($order) {
            $order->awb_code = $awb;
            $order->courier_name = $courier;
            $order->shipping_status = $status;
            $order->status = 'shipped';
            $order->save();

            \Log::info('Order Updated', [
                'order_id' => $order->id,
                'awb' => $awb,
                'status' => $status
            ]);
        }


        return response()->json([
            'success' => true,
            'data'=> $data
        ]);
    }
}
