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

        $awb = $request->awb;
        $status = $request->current_status;

        $order = Order::where('awb_code', $awb)->first();

        if ($order) {

            $order->shipping_status = $status;
            $order->save();
        }

        return response()->json([
            'success' => true
        ]);
    }
}
