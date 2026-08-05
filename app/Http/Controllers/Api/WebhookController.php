<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\ReturnOrder;
use App\Services\ShiprocketService;
use App\Jobs\OrderStatusNotificationJob;

class WebhookController extends Controller
{
    public function handle(Request $request, ShiprocketService $shiprocket)
    {
        if (!$this->isAuthorized($request)) {
            \Log::warning('Shiprocket webhook unauthorized', [
                'ip' => $request->ip(),
                'headers' => [
                    'authorization' => $request->header('Authorization'),
                    'x-api-key' => $request->header('X-Api-Key'),
                ],
                'query_token' => $request->query('token') ? 'present' : 'missing',
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        \Log::info('Shiprocket Webhook', $request->all());
        $data = $request->all();

        // Return / reverse shipment updates
        if (isset($data['channel_order_id']) && empty($data['awb']) && empty($data['shipment_status']) && empty($data['current_status'])) {
            return $this->handleReturnWebhook($data);
        }

        // Also handle nested Shiprocket formats
        $awb = $data['awb']
            ?? ($data['awb_code'] ?? null)
            ?? data_get($data, 'shipment.awb')
            ?? null;

        $status = $data['shipment_status']
            ?? ($data['current_status'] ?? null)
            ?? ($data['status'] ?? null)
            ?? data_get($data, 'shipment.status')
            ?? null;

        $courier = $data['courier_name']
            ?? ($data['courier'] ?? null)
            ?? data_get($data, 'shipment.courier')
            ?? null;

        $orderId = $data['order_id']
            ?? ($data['channel_order_id'] ?? null)
            ?? data_get($data, 'shipment.order_id')
            ?? null;

        $srShipmentId = $data['shipment_id']
            ?? data_get($data, 'shipment.shipment_id')
            ?? null;

        $order = $this->findOrder($orderId, $awb, $srShipmentId);

        if (!$order) {
            \Log::error('Order not found for Shiprocket webhook', [
                'order_id' => $orderId,
                'awb' => $awb,
                'shipment_id' => $srShipmentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $previousStatus = $order->status;
        $hadAwb = !empty($order->awb_code);

        if ($awb) {
            $order->awb_code = $awb;
        }
        if ($courier) {
            $order->courier_name = $courier;
        }
        if ($srShipmentId && empty($order->shipment_id)) {
            $order->shipment_id = $srShipmentId;
        }
        if ($status !== null && $status !== '') {
            $order->shipping_status = $status;
        }

        if (!empty($data['etd'])) {
            try {
                $order->expected_delivery_date = \Carbon\Carbon::parse($data['etd']);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $mapped = $this->mapShiprocketStatus((string) $status, $awb, $hadAwb);

        if ($mapped['order_status']) {
            $order->status = $mapped['order_status'];
        }

        if ($mapped['order_status'] === 'delivered' && empty($order->delivered_at)) {
            $order->delivered_at = now();
        }

        $order->save();

        // Optional tracking enrichment
        if (!empty($order->awb_code)) {
            try {
                $tracking = $shiprocket->trackByAwb($order->awb_code);
                $trackingData = $tracking['tracking']['tracking_data'] ?? [];
                $etd = $trackingData['etd'] ?? null;
                $shipmentTrack = $trackingData['shipment_track'][0] ?? [];
                $courierName = $shipmentTrack['courier_name'] ?? null;

                if ($etd) {
                    $order->expected_delivery_date = \Carbon\Carbon::parse($etd);
                }
                if ($courierName) {
                    $order->courier_name = $courierName;
                }
                $order->save();
            } catch (\Throwable $e) {
                \Log::warning('Shiprocket track failed in webhook', [
                    'awb' => $order->awb_code,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $event = $mapped['event'];
        if ($event && $this->shouldNotify($previousStatus, $event, $hadAwb, !empty($order->awb_code))) {
            OrderStatusNotificationJob::dispatch($order->id, $event);
            \Log::info('Order status notification dispatched', [
                'order_id' => $order->id,
                'from' => $previousStatus,
                'to' => $order->status,
                'event' => $event,
                'shiprocket_status' => $status,
                'awb' => $order->awb_code,
            ]);
        }

        \Log::info('Order Updated from Shiprocket webhook', [
            'order_id' => $order->id,
            'awb' => $order->awb_code,
            'status' => $status,
            'order_status' => $order->status,
        ]);

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'status' => $order->status,
            'event' => $event,
        ]);
    }

    /**
     * Shiprocket often cannot send custom Authorization headers.
     * Accept token via header OR ?token= query string.
     */
    private function isAuthorized(Request $request): bool
    {
        $expected = (string) env('SHIPROCKET_WEBHOOK_TOKEN', 'dhirago_shiprocket_secure_12345');

        $candidates = [
            $request->header('Authorization'),
            $request->bearerToken(),
            $request->header('X-Api-Key'),
            $request->query('token'),
            $request->input('token'),
        ];

        foreach ($candidates as $token) {
            if (!$token) {
                continue;
            }
            $token = trim((string) $token);
            // Allow "Bearer xxx" or raw token
            if (str_starts_with(strtolower($token), 'bearer ')) {
                $token = trim(substr($token, 7));
            }
            if (hash_equals($expected, $token)) {
                return true;
            }
        }

        return false;
    }

    private function findOrder($orderId, $awb, $srShipmentId): ?Order
    {
        $order = null;
        $prefix = (string) env('ORDER_PREFIX', '');

        if ($orderId) {
            $orderNumber = str_replace($prefix, '', (string) $orderId);
            $order = Order::where('order_number', $orderNumber)->first();
            if (!$order && $prefix !== '') {
                $order = Order::where('order_number', $orderId)->first();
            }
        }

        if (!$order && $awb) {
            $order = Order::where('awb_code', $awb)->first();
        }

        if (!$order && $srShipmentId) {
            $order = Order::where('shipment_id', $srShipmentId)->first();
        }

        return $order;
    }

    private function handleReturnWebhook(array $data)
    {
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

            $return->courier = $data['company_name'] ?? null;
            $return->save();
        }

        return response()->json(['success' => true]);
    }

    /**
     * @return array{order_status:?string,event:?string}
     */
    private function mapShiprocketStatus(string $status, ?string $awb, bool $hadAwb): array
    {
        $s = strtoupper(trim($status));

        // Delivered
        if (str_contains($s, 'DELIVERED') || $s === 'DLVRD') {
            return ['order_status' => 'delivered', 'event' => 'delivered'];
        }

        // Out for delivery
        if (
            str_contains($s, 'OUT FOR DELIVERY')
            || str_contains($s, 'OUT_FOR_DELIVERY')
            || $s === 'OFD'
            || str_contains($s, 'OUTFORDELIVERY')
        ) {
            return ['order_status' => 'out_for_delivery', 'event' => 'out_for_delivery'];
        }

        // Shipment booked = AWB assigned from Shiprocket dashboard (first time)
        // PENDING with AWB, PICKUP GENERATED, SHIPPED, etc.
        $awbAssignedNow = !$hadAwb && !empty($awb);
        $looksBooked = (
            str_contains($s, 'PICKUP')
            || str_contains($s, 'PICKED')
            || str_contains($s, 'SHIPPED')
            || str_contains($s, 'IN TRANSIT')
            || str_contains($s, 'IN_TRANSIT')
            || str_contains($s, 'REACHED')
            || str_contains($s, 'AWB')
            || str_contains($s, 'LABEL')
            || str_contains($s, 'MANIFEST')
            || str_contains($s, 'ASSIGNED')
            || str_contains($s, 'BOOKED')
            || str_contains($s, 'DISPATCH')
            || $s === 'PENDING' // Shiprocket often sends PENDING once AWB is assigned
        );

        if ($awbAssignedNow || ($looksBooked && !empty($awb))) {
            return ['order_status' => 'shipped', 'event' => 'shipment_booked'];
        }

        // NEW / empty / no AWB yet — ignore, no mail
        return ['order_status' => null, 'event' => null];
    }

    private function shouldNotify(?string $previousStatus, string $event, bool $hadAwb, bool $hasAwb): bool
    {
        $previousStatus = $previousStatus ?: 'new';

        return match ($event) {
            'shipment_booked' => !$hadAwb && $hasAwb && !in_array($previousStatus, [
                'out_for_delivery', 'delivered',
            ], true),
            'out_for_delivery' => $previousStatus !== 'out_for_delivery'
                && $previousStatus !== 'delivered',
            'delivered' => $previousStatus !== 'delivered',
            default => false,
        };
    }
}
