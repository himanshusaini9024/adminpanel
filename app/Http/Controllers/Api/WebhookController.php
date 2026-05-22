<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

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

        $awb = $data['awb'] ?? null;

        $status = $data['shipment_status'] ?? null;

        $courier = $data['courier_name'] ?? null;

        $orderId = $data['order_id'] ?? null;

        // remove DHI-
        $orderNumber = str_replace('DHI-', '', $orderId);

        $order = Order::where('order_number', $orderNumber)->first();

        if ($order) {

            $order->awb_code = $awb;

            $order->courier_name = $courier;

            $order->shipment_status = $status;

            $order->save();

            \Log::info('Order Updated', [
                'order_id' => $order->id,
                'awb' => $awb,
                'status' => $status
            ]);
        }

        return response()->json([
            'success' => true
        ]);
    }
}
