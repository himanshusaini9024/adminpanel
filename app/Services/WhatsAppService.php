<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
   
    public function sendOrderConfirmation($phone, $name, $orderNumber, $amount)
{
    try {

        $response = Http::withToken(env('WHATSAPP_TOKEN'))
            ->post("https://graph.facebook.com/v22.0/" . env('WHATSAPP_PHONE_ID') . "/messages",
                [
                    "messaging_product" => "whatsapp",
                    "to" => $phone,
                    "type" => "template",
                    "template" => [
                        "name" => "order_confirm",
                        "language" => [
                            "code" => "en"
                        ],
                        "components" => [
                            [
                                "type" => "body",
                                "parameters" => [
                                    [
                                        "type" => "text",
                                        "text" => $name
                                    ],
                                    [
                                        "type" => "text",
                                        "text" => $orderNumber
                                    ],
                                    [
                                        "type" => "text",
                                        "text" => (string)$amount
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);

        \Log::info('WhatsApp API Response', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        return $response;

    } catch (\Throwable $e) {

        \Log::error('WhatsApp Exception', [
            'message' => $e->getMessage(),
        ]);

        throw $e;
    }
}
}
