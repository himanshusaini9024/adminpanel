<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected ShiprocketService $shiprocket;

    public function __construct(ShiprocketService $shiprocket)
    {
        $this->shiprocket = $shiprocket;
    }

    public function createOrder(array $data): Order
    {
        DB::beginTransaction();

        try {
            Log::info('Order Data', $data);

            $order = Order::create([
                'customer_id'         => $data['customer_id'] ?? null,
                'razorpay_payment_id' => $data['payment_id'] ?? null,
                'razorpay_order_id'   => $data['razorpay_order_id'] ?? null,
                'sub_total'           => $data['sub_total'] ?? 0,
                'total_amount'        => $data['total_amount'] ?? 0,
                'quantity'            => $data['quantity'] ?? 0,
                'city'                => $data['city'] ?? null,
                'payment_method'      => $data['payment_method'] ?? null,
                'payment_status'      => $data['payment_status'] ?? null,
                'status'              => 'new',
                'first_name'          => $data['first_name'] ?? null,
                'last_name'           => $data['last_name'] ?? null,
                'phone'               => $data['phone'] ?? null,
                'address1'            => $data['address1'] ?? null,
                'address2'           => $data['address2'] ?? null,
                'state'               => $data['state'] ?? null,
                'country'             => 'IND',
                'email'               => $data['email'] ?? null,
                'post_code'           => $data['pincode'] ?? null,
            ]);

            $order->order_number = 100 + $order->id;
            $order->save();

            $shiprocketItems = [];

            foreach ($data['items'] as $item) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                    'product_id'   => $item['id'],
                    'sku'          => $item['sku'],
                    'image'        => $item['thumb']['url'] ?? null,
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'size'         => $item['size'] ?? null,
                    'color'        => $item['color'] ?? null,
                ]);

                $shiprocketItems[] = [
                    'name'          => $item['name'],
                    'sku'           => $item['sku'],
                    'units'         => $item['quantity'],
                    'selling_price' => $item['price'],
                ];
            }

            if (env('SHIPMENT_LIVE', false)) {
                $shiprocketResponse = $this->shiprocket->createOrder($order, $shiprocketItems);

                Log::info('Shiprocket Response', ['response' => $shiprocketResponse]);

                if (isset($shiprocketResponse['shipment_id'])) {
                    $order->shipment_id = $shiprocketResponse['shipment_id'];
                    $order->awb_code = $shiprocketResponse['awb_code'] ?? null;
                    $order->save();
                }
            } else {
                Log::info('Shiprocket disabled');
            }

            DB::commit();

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Order Error', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);

            throw $e;
        }
    }
}