<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrderService
{
    protected ShiprocketService $shiprocket;
    protected FirstOrderDiscountService $firstOrderDiscount;
    protected CouponDiscountService $couponDiscount;

    public function __construct(
        ShiprocketService $shiprocket,
        FirstOrderDiscountService $firstOrderDiscount,
        CouponDiscountService $couponDiscount
    ) {
        $this->shiprocket = $shiprocket;
        $this->firstOrderDiscount = $firstOrderDiscount;
        $this->couponDiscount = $couponDiscount;
    }

    public function createOrder(array $data): Order
    {
        DB::beginTransaction();

        try {
            Log::info('Order Data', $data);

            $items = $data['items'] ?? [];
            $customerId = Auth::guard('customer')->id() ?: (isset($data['customer_id']) ? (int) $data['customer_id'] : null);
            $orderType = $data['order_type'] ?? 'normal';
            $skipFirstOrder = ($orderType === 'exchange')
                || !empty($data['parent_order_id'])
                || !empty($data['skip_first_order_discount']);

            $computedSubtotal = $this->firstOrderDiscount->subtotalFromItems($items);
            if ($computedSubtotal <= 0) {
                $computedSubtotal = round((float) ($data['sub_total'] ?? 0), 2);
            }

            // When feature is disabled, applyForOrder returns full subtotal (no discount).
            $pricing = $this->firstOrderDiscount->applyForOrder(
                $computedSubtotal,
                $customerId,
                $skipFirstOrder
            );

            $afterFirst = $pricing['total'];
            $couponResult = $this->couponDiscount->applyForOrder(
                $afterFirst,
                $data['coupon_code'] ?? null,
                $customerId
            );

            // If client sent a coupon code that is invalid/already used, fail the order.
            $requestedCode = $this->couponDiscount->normalizeCode($data['coupon_code'] ?? null);
            if ($requestedCode && !$couponResult['applied']) {
                throw new \InvalidArgumentException(
                    $couponResult['message'] ?: 'Invalid coupon code'
                );
            }

            $firstDiscount = $pricing['applied'] ? (float) $pricing['discount'] : 0.0;
            $couponAmount = $couponResult['applied'] ? (float) $couponResult['discount'] : 0.0;
            $totalDiscount = round($firstDiscount + $couponAmount, 2);
            $payableTotal = round(max(0, $computedSubtotal - $totalDiscount), 2);

            $order = Order::create([
                'customer_id'         => $customerId,
                'razorpay_payment_id' => $data['payment_id'] ?? null,
                'razorpay_order_id'   => $data['razorpay_order_id'] ?? null,
                'sub_total'           => $pricing['sub_total'],
                'total_amount'        => $payableTotal,
                'coupon'              => $totalDiscount > 0 ? $totalDiscount : ($data['coupon'] ?? null),
                'coupon_code'         => $couponResult['applied'] ? $couponResult['code'] : null,
                'quantity'            => $data['quantity'] ?? 0,
                'city'                => $data['city'] ?? null,
                'payment_method'      => $data['payment_method'] ?? null,
                'payment_status'      => $data['payment_status'] ?? null,
                'status'              => 'new',
                'first_name'          => $data['first_name'] ?? null,
                'last_name'           => $data['last_name'] ?? null,
                'phone'               => $data['phone'] ?? null,
                'address1'            => $data['address1'] ?? null,
                'address2'            => $data['address2'] ?? null,
                'state'               => $data['state'] ?? null,
                'country'             => 'IND',
                'email'               => $data['email'] ?? null,
                'post_code'           => $data['pincode'] ?? null,
                'order_type'          => $orderType,
                'parent_order_id'     => $data['parent_order_id'] ?? null,
            ]);

            $order->order_number = env('ORDER_SERIES') + $order->id;
            $order->save();

            if ($couponResult['applied'] && $customerId && $couponResult['coupon_id']) {
                $this->couponDiscount->recordRedemption(
                    (int) $couponResult['coupon_id'],
                    (int) $customerId,
                    (int) $order->id
                );
            }

            $shiprocketItems = [];

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                    'product_id'   => $item['id'],
                    'name'         => $item['name'],
                    'sku'          => $item['sku'],
                    'image'        => media_path($item['thumb']['url'] ?? null) ?: null,
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'size'         => $item['size'] ?? null,
                    'color'        => $item['color'] ?? null,
                ]);

                $shiprocketItems[] = [
                    'name'          => $item['name'],
                    'sku'           => $item['sku'] . '-' . ($item['size'] ?? ''),
                    'units'         => $item['quantity'],
                    'selling_price' => $item['price'],
                ];
            }

            if (env('SHIPMENT_LIVE', false)) {
                $shiprocketResponse = $this->shiprocket->createOrder($order, $shiprocketItems);

                Log::info('Shiprocket Response', ['response' => $shiprocketResponse]);

                if (isset($shiprocketResponse['shipment_id'])) {
                    $order->shipment_id = $shiprocketResponse['shipment_id'];
                    if (!empty($shiprocketResponse['awb_code'])) {
                        $order->awb_code = $shiprocketResponse['awb_code'];
                    }
                    $order->shipping_status = $shiprocketResponse['status'] ?? 'NEW';
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
