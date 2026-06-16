<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShiprocketService
{
    protected $baseUrl;
    protected $email;
    protected $password;

    public function __construct()
    {
        $this->baseUrl = env('SHIPROCKET_BASE_URL');
        $this->email = env('SHIPROCKET_EMAIL');
        $this->password = env('SHIPROCKET_PASSWORD');
    }

    public function login()
    {
        $response = Http::post($this->baseUrl . '/auth/login', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        return $response->json()['token'];
    }

    public function createOrder($order, $items)
    {
        $token = $this->login();
    Log::info('token', [
                    'token' => $token
                ]);
        $payload = [
            "order_id" => "DHI-" . $order->order_number,
            "order_date" => now()->format('Y-m-d H:i'),
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
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post(
            $this->baseUrl . '/orders/create/adhoc',
            $payload
        )->json();
    }

    public function createReturn($return, $sku)
    {
        $token = $this->login();

        $order = $return->order;

        $response = Http::withToken($token)
            ->post(
                'https://apiv2.shiprocket.in/v1/external/orders/create/return',
                [

                    "order_id" => "RETURN_" . $order->id,

                    "order_date" => now(),

                    // CUSTOMER PICKUP ADDRESS
                    "pickup_customer_name" =>
                    $order->first_name . ' ' .
                        $order->last_name,

                    "pickup_address" => $order->address1 . ',' . $order->address2,

                    "pickup_city" => $order->city,

                    "pickup_state" => $order->state,

                    "pickup_pincode" => $order->post_code,

                    "pickup_country" => "India",

                    "pickup_email" => $order->email,

                    "pickup_phone" => $order->phone,

                    // YOUR WAREHOUSE
                    "shipping_customer_name" => "Dhirago",

                    "shipping_address" =>
                    "Home",

                    "shipping_city" => "Udaipur",

                    "shipping_state" => "Rajasthan",

                    "shipping_pincode" => "313001",

                    "shipping_country" => "India",

                    "shipping_email" => "admin@test.com",

                    "shipping_phone" => "9999999999",

                    // ITEMS
                    "order_items" => [
                        [
                            "name" => "Return Product",
                            "sku" => $sku,
                            "units" => 1,
                            "selling_price" =>
                            $order->total_amount
                        ]
                    ],

                    "payment_method" => "Prepaid",

                    "sub_total" => $order->total_amount,

                    "length" => 10,
                    "breadth" => 10,
                    "height" => 10,
                    "weight" => 0.5
                ]
            );

        return $response->json();
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
