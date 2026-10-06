<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\ReturnOrder;
use App\Services\ShiprocketService;
use App\Jobs\OrderStatusNotificationJob;
use App\Models\User;
use App\Notifications\StatusNotification;
use Illuminate\Support\Facades\Notification;

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

        // Return / reverse shipment updates (tracking payloads include AWB + is_return / RET* order_id)
        if ($this->isReturnWebhook($data)) {
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

        $statusId = $data['current_status_id'] ?? ($data['shipment_status_id'] ?? null);
        $mapped = $this->mapShiprocketStatus((string) $status, $statusId, $awb, $hadAwb);

        if ($mapped['order_status'] && !$order->canMoveToStatus($mapped['order_status'])) {
            \Log::info('Shiprocket webhook status ignored (would move order backwards)', [
                'order_id' => $order->id,
                'current' => $order->status,
                'incoming' => $mapped['order_status'],
                'shiprocket_status' => $status,
            ]);
            $mapped = ['order_status' => null, 'event' => null];
        }

        if ($mapped['order_status']) {
            $order->status = $mapped['order_status'];
        }

        if ($mapped['order_status'] === 'delivered' && empty($order->delivered_at)) {
            $order->delivered_at = now();
        }
        if (in_array($mapped['order_status'], ['rto', 'rto_delivered'], true) && empty($order->rto_initiated_at)) {
            $order->rto_initiated_at = now();
        }
        if ($mapped['order_status'] === 'rto_delivered' && empty($order->rto_delivered_at)) {
            $order->rto_delivered_at = now();
        }

        $remark = $this->courierRemark($data);
        if ($remark !== null) {
            $order->courier_remark = $remark;
        }

        $order->save();

        if ($order->status !== $previousStatus) {
            $this->alertAdmin($order);
        }

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

    /**
     * Detect reverse / return shipment webhooks from Shiprocket.
     */
    private function isReturnWebhook(array $data): bool
    {
        if (!empty($data['is_return']) || (string) ($data['is_return'] ?? '') === '1') {
            return true;
        }

        $orderId = (string) (
            $data['order_id']
            ?? $data['channel_order_id']
            ?? data_get($data, 'shipment.order_id')
            ?? ''
        );

        if ($orderId !== '' && preg_match('/^RET\d+/i', $orderId)) {
            return true;
        }

        $status = strtoupper((string) (
            $data['current_status']
            ?? $data['shipment_status']
            ?? $data['status']
            ?? ''
        ));

        // "RETURN TO ORIGIN" is an undelivered forward parcel coming back (RTO), not a customer return.
        if ($status !== '' && str_starts_with($status, 'RETURN') && !str_contains($status, 'ORIGIN')) {
            return true;
        }

        // Legacy format: channel_order_id only, no AWB/status
        if (isset($data['channel_order_id']) && empty($data['awb']) && empty($data['shipment_status']) && empty($data['current_status'])) {
            return true;
        }

        return false;
    }

    private function handleReturnWebhook(array $data)
    {
        $awb = $data['awb']
            ?? $data['awb_code']
            ?? data_get($data, 'shipment.awb')
            ?? null;

        $srOrderId = $data['sr_order_id']
            ?? $data['order_id']
            ?? null;

        // Tracking payloads put our channel id in order_id (e.g. RET1O4T260924143951)
        $channelOrderId = null;
        foreach (['order_id', 'channel_order_id'] as $key) {
            $value = (string) ($data[$key] ?? '');
            if ($value !== '' && preg_match('/^RET\d+/i', $value)) {
                $channelOrderId = $value;
                break;
            }
        }

        $srShipmentId = $data['shipment_id']
            ?? data_get($data, 'shipment.shipment_id')
            ?? null;

        $return = $this->findReturn($channelOrderId, $awb, $srOrderId, $srShipmentId);

        if (!$return) {
            \Log::error('Return not found for Shiprocket webhook', [
                'channel_order_id' => $channelOrderId,
                'awb' => $awb,
                'sr_order_id' => $srOrderId,
                'shipment_id' => $srShipmentId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Return not found',
            ], 404);
        }

        $previousStatus = $return->status;
        $status = strtoupper((string) (
            $data['current_status']
            ?? $data['shipment_status']
            ?? $data['status']
            ?? ''
        ));

        $newStatus = $this->mapReturnStatus($status);

        if ($awb && empty($return->reverse_awb)) {
            $return->reverse_awb = $awb;
        }
        if ($srShipmentId && empty($return->reverse_shipment_id)) {
            $return->reverse_shipment_id = $srShipmentId;
        }
        if (is_numeric($srOrderId) && empty($return->reverse_order_id)) {
            $return->reverse_order_id = $srOrderId;
        }

        $courier = $data['courier_name']
            ?? $data['company_name']
            ?? $data['courier']
            ?? null;
        if ($courier) {
            $return->courier = $courier;
        }

        if ($newStatus) {
            $return->status = $newStatus;
        }

        $return->save();

        if ($newStatus) {
            $return->notifyCustomer($previousStatus);
        }

        \Log::info('Return updated from Shiprocket webhook', [
            'return_id' => $return->id,
            'from' => $previousStatus,
            'to' => $return->status,
            'shiprocket_status' => $status,
            'awb' => $return->reverse_awb,
        ]);

        return response()->json([
            'success' => true,
            'return_id' => $return->id,
            'status' => $return->status,
        ]);
    }

    private function findReturn(?string $channelOrderId, $awb, $srOrderId, $srShipmentId): ?ReturnOrder
    {
        $return = null;

        // RET{returnId}O{orderId}T{ymdHis}
        if ($channelOrderId && preg_match('/^RET(\d+)O(\d+)/i', $channelOrderId, $m)) {
            $return = ReturnOrder::where('id', (int) $m[1])
                ->where('order_id', (int) $m[2])
                ->first();

            if (!$return) {
                $return = ReturnOrder::find((int) $m[1]);
            }
        }

        if (!$return && $awb) {
            $return = ReturnOrder::where('reverse_awb', $awb)->first();
        }

        if (!$return && $srShipmentId) {
            $return = ReturnOrder::where('reverse_shipment_id', $srShipmentId)->first();
        }

        if (!$return && $srOrderId && is_numeric($srOrderId)) {
            $return = ReturnOrder::where('reverse_order_id', $srOrderId)->first();
        }

        return $return;
    }

    private function mapReturnStatus(string $status): ?string
    {
        $s = strtoupper(trim($status));
        if ($s === '') {
            return null;
        }

        if (str_contains($s, 'CANCEL')) {
            return 'rejected';
        }

        // RETURN DELIVERED / DELIVERED — item reached warehouse
        if (str_contains($s, 'DELIVERED') || $s === 'DLVRD') {
            return 'delivered';
        }

        // RETURN PICKED UP / PICKED UP — check before generic PICKUP
        if (
            str_contains($s, 'PICKED UP')
            || str_contains($s, 'PICKED_UP')
            || preg_match('/\bPICKED\b/', $s)
            || $s === '42'
        ) {
            return 'picked_up';
        }

        if (str_contains($s, 'TRANSIT') || str_contains($s, 'IN TRANSIT')) {
            return 'in_transit';
        }

        // OUT FOR PICKUP / PICKUP SCHEDULED / RETURN PENDING / RETURN INITIATED
        if (
            str_contains($s, 'OUT FOR PICKUP')
            || str_contains($s, 'PICKUP')
            || str_contains($s, 'RETURN PENDING')
            || str_contains($s, 'RETURN INITIATED')
            || str_contains($s, 'RETURN QUEUED')
        ) {
            return 'pickup_scheduled';
        }

        return null;
    }

    /**
     * Map a Shiprocket forward-shipment status to our order status + customer event.
     * Order matters: "UNDELIVERED" and "RTO DELIVERED" both contain "DELIVERED".
     *
     * @return array{order_status:?string,event:?string}
     */
    private function mapShiprocketStatus(string $status, $statusId, ?string $awb, bool $hadAwb): array
    {
        $s = preg_replace('/\s+/', ' ', strtoupper(trim(str_replace(['_', '-'], ' ', $status))));

        // Only status text is reliable across courier payloads; numeric ids are a fallback.
        if ($s === '' && is_numeric($statusId)) {
            $s = match ((int) $statusId) {
                7 => 'DELIVERED',
                9 => 'RTO INITIATED',
                10, 14 => 'RTO DELIVERED',
                12 => 'LOST',
                17 => 'OUT FOR DELIVERY',
                21 => 'UNDELIVERED',
                24 => 'DESTROYED',
                25 => 'DAMAGED',
                default => '',
            };
        }

        $isRto = preg_match('/^RTO\b/', $s) || str_contains($s, 'RETURN TO ORIGIN');

        // Undelivered parcel is back at our warehouse
        if ($isRto && preg_match('/DELIVERED|ACKNOWLEDGED|RECEIVED/', $s)) {
            return ['order_status' => 'rto_delivered', 'event' => null];
        }

        // RTO initiated / in transit / out for delivery to warehouse / RTO NDR
        if ($isRto) {
            return ['order_status' => 'rto', 'event' => 'rto_initiated'];
        }

        // Delivery attempt failed (NDR) — courier normally retries
        if (
            str_contains($s, 'UNDELIVERED')
            || str_contains($s, 'NOT DELIVERED')
            || str_contains($s, 'DELIVERY FAILED')
            || str_contains($s, 'FAILED DELIVERY')
            || preg_match('/\bNDR\b/', $s)
        ) {
            return ['order_status' => 'undelivered', 'event' => 'undelivered'];
        }

        if (preg_match('/\b(LOST|DAMAGED|DESTROYED|DISPOSED)\b/', $s)) {
            return ['order_status' => 'lost', 'event' => null];
        }

        // Shipment cancelled in Shiprocket (often to re-book with another courier):
        // shipping_status records it, the order itself stays as it is.
        if (str_contains($s, 'CANCEL')) {
            return ['order_status' => null, 'event' => null];
        }

        // Delivered
        if ((str_contains($s, 'DELIVERED') && !str_contains($s, 'PARTIAL')) || $s === 'DLVRD') {
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
            // Once per failed attempt (courier goes out again in between)
            'undelivered' => $previousStatus !== 'undelivered',
            'rto_initiated' => !in_array($previousStatus, ['rto', 'rto_delivered'], true),
            default => false,
        };
    }

    /**
     * Latest courier remark, e.g. "Customer not available" / "Address incomplete".
     */
    private function courierRemark(array $data): ?string
    {
        $scans = $data['scans'] ?? null;
        $lastScan = is_array($scans) && $scans !== [] ? end($scans) : null;

        $remark = $data['ndr_reason']
            ?? $data['remarks']
            ?? (is_array($lastScan) ? ($lastScan['activity'] ?? null) : null);

        $remark = trim((string) $remark);

        return $remark === '' ? null : mb_substr($remark, 0, 255);
    }

    /**
     * Bell notification in the admin panel for courier problems that need action.
     */
    private function alertAdmin(Order $order): void
    {
        $title = match ($order->status) {
            'undelivered' => "Delivery failed for order #{$order->order_number}",
            'rto' => "Order #{$order->order_number} is returning to warehouse (RTO)",
            'rto_delivered' => "RTO order #{$order->order_number} is back at the warehouse",
            'lost' => "Order #{$order->order_number} reported lost/damaged by courier",
            default => null,
        };

        if (!$title) {
            return;
        }

        try {
            $admin = User::where('role', 'admin')->first();
            if ($admin) {
                Notification::send($admin, new StatusNotification([
                    'title' => $title,
                    'actionURL' => route('order.show', $order->id),
                    'fas' => 'fa-truck',
                ]));
            }
        } catch (\Throwable $e) {
            \Log::warning('Admin alert failed for Shiprocket webhook', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
