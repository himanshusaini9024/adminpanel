<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketService
{
    protected $baseUrl;
    protected $email;
    protected $password;
    protected $orderprefix;

    /** Nickname of the Shiprocket pickup location used for outbound + return warehouse. */
    protected string $pickupLocationName;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) env('SHIPROCKET_BASE_URL'), '/');
        $this->email = env('SHIPROCKET_EMAIL');
        $this->password = env('SHIPROCKET_PASSWORD');
        $this->orderprefix = env('ORDER_PREFIX');
        $this->pickupLocationName = trim((string) (
            config('services.shiprocket.pickup_location')
            ?: env('SHIPROCKET_PICKUP_LOCATION', 'Home')
        )) ?: 'Home';
    }

    public function login()
    {
        $response = Http::post($this->baseUrl . '/auth/login', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        return $response->json()['token'];
    }

    /**
     * Fetch a Shiprocket pickup address (the warehouse where you hand parcels to the courier).
     * Prefers SHIPROCKET_PICKUP_LOCATION nickname, then primary location, then first available.
     *
     * @return array{pickup_location:string,name:string,address:string,address_2:?string,city:string,state:string,pin_code:string,country:string,email:string,phone:string}|null
     */
    public function getPickupAddress(?string $token = null, ?string $locationName = null): ?array
    {
        $token = $token ?: $this->login();
        $wanted = trim((string) ($locationName ?: $this->pickupLocationName));

        $response = Http::withToken($token)
            ->get($this->baseUrl . '/settings/company/pickup');

        if (!$response->successful()) {
            Log::error('Shiprocket pickup list failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $addresses = data_get($response->json(), 'data.shipping_address', []);
        if (!is_array($addresses) || $addresses === []) {
            Log::warning('Shiprocket pickup list empty');
            return null;
        }

        $selected = null;
        foreach ($addresses as $address) {
            if (strcasecmp((string) ($address['pickup_location'] ?? ''), $wanted) === 0) {
                $selected = $address;
                break;
            }
        }

        if (!$selected) {
            foreach ($addresses as $address) {
                if (!empty($address['is_primary_location'])) {
                    $selected = $address;
                    break;
                }
            }
        }

        $selected = $selected ?: $addresses[0];

        return [
            'pickup_location' => (string) ($selected['pickup_location'] ?? $wanted),
            'name' => (string) ($selected['name'] ?? 'Dhirago'),
            'address' => (string) ($selected['address'] ?? ''),
            'address_2' => (string) ($selected['address_2'] ?? ''),
            'city' => (string) ($selected['city'] ?? ''),
            'state' => (string) ($selected['state'] ?? ''),
            'pin_code' => (string) ($selected['pin_code'] ?? ''),
            'country' => (string) ($selected['country'] ?? 'India'),
            'email' => (string) ($selected['email'] ?? ''),
            'phone' => (string) ($selected['phone'] ?? ''),
        ];
    }

    public function createOrder($order, $items)
    {
        $token = $this->login();
        Log::info('token', [
            'token' => $token
        ]);
        $payload = [
            "order_id" => $this->orderprefix . $order->order_number,
            "order_date" => now()->format('Y-m-d H:i'),
            // "pickup_location" => $this->pickupLocationName,
            "pickup_location" => "Home",
            "billing_customer_name" => $order->first_name,
            "billing_last_name" => $order->last_name,
            "billing_address" => $order->address1,
            "billing_address_2" => $order->address2,
            "billing_city" => $order->city,
            "billing_pincode" => $order->post_code,
            "billing_state" => $order->state,
            "billing_country" => "India",
            "billing_email" => $order->email,
            "billing_phone" => $order->phone,
            'shipping_is_billing' => true,

            "shipping_customer_name" => $order->first_name,
            "shipping_last_name" => $order->last_name,
            "shipping_address" => $order->address1,
            "shipping_address_2" => $order->address2,
            "shipping_city" => $order->city,
            "shipping_pincode" => $order->post_code,
            "shipping_state" => $order->state,
            "shipping_country" => "India",
            "shipping_email" => $order->email,
            "shipping_phone" => $order->phone,

            "order_items" => $items,

            "payment_method" =>
            $order->payment_method == "cod"
                ? "COD"
                : "Prepaid",

            "sub_total" => $order->total_amount,

            "length" => 25,
            "breadth" => 20,
            "height" => 3,
            "weight" => 0.5,
        ];
        Log::info('Shiprocket Payload', $payload);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post(
            $this->baseUrl . '/orders/create/adhoc',
            $payload
        );

        Log::info('Shiprocket Raw Response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        Log::info($response->headers());
        Log::info($response->json());
        return $response->json();
    }

    public function createReturn($return, $sku)
    {
        $token = $this->login();
        $order = $return->order;
        $orderItem = $return->relationLoaded('orderItem')
            ? $return->orderItem
            : $return->orderItem()->first();

        $warehouse = $this->getPickupAddress($token);
        if (!$warehouse || $warehouse['address'] === '' || $warehouse['pin_code'] === '') {
            Log::error('Shiprocket createReturn: pickup/warehouse address missing', [
                'pickup_location' => $this->pickupLocationName,
                'warehouse' => $warehouse,
            ]);

            return [
                'status_code' => 0,
                'message' => 'Shiprocket pickup address "' . $this->pickupLocationName . '" not found. Add it under Shiprocket → Settings → Pickup Addresses.',
            ];
        }

        // Unique channel id — reusing RETURN_{order_id} returns an old CANCELLED order (status 27).
        $channelOrderId = 'RET' . $return->id . 'O' . $order->id . 'T' . now()->format('ymdHis');

        [$customerAddress1, $customerAddress2] = $this->splitAddress(
            (string) $order->address1,
            (string) $order->address2
        );
        [$warehouseAddress1, $warehouseAddress2] = $this->splitAddress(
            (string) $warehouse['address'],
            (string) ($warehouse['address_2'] ?? '')
        );

        $payload = [
            'order_id' => $channelOrderId,
            'order_date' => now()->format('Y-m-d H:i'),

            // Customer address — courier picks the product from here
            'pickup_customer_name' => trim($order->first_name . ' ' . $order->last_name) ?: 'Customer',
            'pickup_address' => $customerAddress1,
            'pickup_address_2' => $customerAddress2,
            'pickup_city' => $order->city,
            'pickup_state' => $order->state,
            'pickup_pincode' => $order->post_code,
            'pickup_country' => 'India',
            'pickup_email' => $order->email,
            'pickup_phone' => preg_replace('/\D+/', '', (string) $order->phone),

            // Your Shiprocket Pickup Address — warehouse destination for the reverse shipment
            'shipping_customer_name' => $warehouse['name'] ?: 'Dhirago',
            'shipping_address' => $warehouseAddress1,
            'shipping_address_2' => $warehouseAddress2,
            'shipping_city' => $warehouse['city'],
            'shipping_state' => $warehouse['state'],
            'shipping_pincode' => $warehouse['pin_code'],
            'shipping_country' => $warehouse['country'] ?: 'India',
            'shipping_email' => $warehouse['email'] ?: (string) env('MAIL_FROM_ADDRESS', 'contact@dhirago.com'),
            'shipping_phone' => preg_replace('/\D+/', '', (string) $warehouse['phone']),

            'order_items' => [
                [
                    'name' => mb_substr((string) ($orderItem->name ?? 'Return Product'), 0, 100),
                    'sku' => (string) ($sku ?: ($orderItem->sku ?? ('RET-' . $return->id))),
                    'units' => 1,
                    'selling_price' => (float) ($orderItem->price ?? $order->total_amount),
                ],
            ],

            'payment_method' => 'Prepaid',
            'sub_total' => (float) ($orderItem->price ?? $order->total_amount),
            'length' => 25,
            'breadth' => 20,
            'height' => 3,
            'weight' => 0.5,
        ];

        Log::info('Shiprocket return payload', [
            'channel_order_id' => $channelOrderId,
            'return_id' => $return->id,
            'pickup_location_used' => $warehouse['pickup_location'],
            'warehouse_address' => $warehouseAddress1,
            'warehouse_pincode' => $warehouse['pin_code'],
            'customer_pincode' => $order->post_code,
        ]);

        $response = Http::withToken($token)
            ->post($this->baseUrl . '/orders/create/return', $payload);

        $body = $response->json() ?? [];
        Log::info('Shiprocket return response', [
            'http_status' => $response->status(),
            'body' => $body,
        ]);

        $statusCode = (int) ($body['status_code'] ?? 0);
        // 21 Return Pending, 22 Return Initiated, 23 Return Pickup Queued, 28 Return Pickup Generated
        $createdOk = $response->successful()
            && !empty($body['shipment_id'])
            && in_array($statusCode, [21, 22, 23, 28], true);

        if (!$createdOk) {
            $body['message'] = $body['message']
                ?? ('Shiprocket return failed: ' . ($body['status'] ?? 'unknown') . ' (code ' . $statusCode . ')');
            return $body;
        }

        // Assign reverse AWB so courier pickup is actually scheduled
        $awbResponse = Http::withToken($token)->post($this->baseUrl . '/courier/assign/awb', [
            'shipment_id' => $body['shipment_id'],
            'is_return' => 1,
        ]);

        $awbBody = $awbResponse->json() ?? [];
        Log::info('Shiprocket return AWB assign', [
            'shipment_id' => $body['shipment_id'],
            'http_status' => $awbResponse->status(),
            'body' => $awbBody,
        ]);

        $awbAssigned = (int) data_get($awbBody, 'awb_assign_status') === 1
            || !empty(data_get($awbBody, 'response.data.awb_code'));

        if (!$awbAssigned) {
            return [
                'status_code' => 24, // Return Pickup Error
                'order_id' => $body['order_id'] ?? null,
                'shipment_id' => $body['shipment_id'] ?? null,
                'status' => $body['status'] ?? 'RETURN PENDING',
                'message' => 'Return created but AWB assignment failed: '
                    . (data_get($awbBody, 'message')
                        ?: data_get($awbBody, 'response.data.awb_assign_error')
                        ?: json_encode($awbBody)),
            ];
        }

        $body['awb_code'] = data_get($awbBody, 'response.data.awb_code');
        $body['courier_name'] = data_get($awbBody, 'response.data.courier_name')
            ?: data_get($awbBody, 'response.data.courier_brand_name');
        $body['company_name'] = $body['courier_name'] ?: ($body['company_name'] ?? null);
        $body['channel_order_id'] = $channelOrderId;
        $body['message'] = 'Reverse pickup scheduled'
            . ($body['awb_code'] ? ' (AWB ' . $body['awb_code'] . ')' : '');

        return $body;
    }

    /**
     * Shiprocket street address fields are limited (~80 chars).
     *
     * @return array{0:string,1:string}
     */
    private function splitAddress(string $line1, string $line2 = ''): array
    {
        $full = trim(preg_replace('/\s+/', ' ', trim($line1 . ' ' . $line2)) ?? '');
        if ($full === '') {
            return ['', ''];
        }

        if (mb_strlen($full) <= 80) {
            $secondary = trim($line2);
            if ($secondary !== '' && strcasecmp($secondary, $full) !== 0 && mb_strlen(trim($line1)) <= 80) {
                return [mb_substr(trim($line1), 0, 80), mb_substr($secondary, 0, 80)];
            }
            return [$full, ''];
        }

        return [
            mb_substr($full, 0, 80),
            mb_substr($full, 80, 80),
        ];
    }

    public function trackByAwb($awb)
    {
        $token = $this->login();

        $response = Http::withToken($token)
            ->get(
                $this->baseUrl .
                    '/courier/track/awb/' . $awb
            );

        return $response->json();
    }
}
