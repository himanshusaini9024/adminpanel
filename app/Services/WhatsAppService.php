<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Normalize Indian phone to WhatsApp format (91xxxxxxxxxx).
     */
    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $digits = ltrim($digits, '0');

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        return $digits;
    }

    protected function apiUrl(): string
    {
        return 'https://graph.facebook.com/v22.0/' . env('WHATSAPP_PHONE_ID') . '/messages';
    }

    protected function token(): string
    {
        return (string) env('WHATSAPP_TOKEN');
    }

    public function sendOrderConfirmation($phone, $name, $orderNumber, $amount)
    {
        try {
            $phone = $this->normalizePhone($phone);

            $response = Http::withToken($this->token())
                ->post($this->apiUrl(), [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => 'order_confirm',
                        'language' => [
                            'code' => 'en',
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $name],
                                    ['type' => 'text', 'text' => $orderNumber],
                                    ['type' => 'text', 'text' => (string) $amount],
                                ],
                            ],
                        ],
                    ],
                ]);

            Log::info('WhatsApp API Response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('WhatsApp Exception', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send a free-form text message.
     * ONLY delivers if the customer messaged your WhatsApp business number
     * within the last 24 hours (customer-care window). Outside that window
     * Meta may still return HTTP 200 / wamid, but the phone never receives it.
     * For reminders/offers always prefer sendTemplateMessage().
     */
    public function sendTextMessage(?string $phone, string $text)
    {
        $phone = $this->normalizePhone($phone);

        if ($phone === '' || trim($text) === '') {
            throw new \InvalidArgumentException('Phone and message are required');
        }

        // WhatsApp text body max ~4096; keep safe
        $text = mb_substr(trim($text), 0, 4000);

        try {
            $response = Http::withToken($this->token())
                ->post($this->apiUrl(), [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'recipient_type' => 'individual',
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $text,
                    ],
                ]);

            Log::info('WhatsApp text response', [
                'status' => $response->status(),
                'body' => $response->json(),
                'to' => $phone,
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('WhatsApp text exception', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send an approved WhatsApp template (works anytime — needed for offers/reminders).
     *
     * Default template expectation (create in Meta Business Manager):
     *   Name: cart_reminder  (or set WHATSAPP_REMINDER_TEMPLATE)
     *   Language: en
     *   Body: Hi {{1}}, {{2}}
     *
     * @param  array<int, string>  $bodyParams  Values for {{1}}, {{2}}, ...
     */
    public function sendTemplateMessage(?string $phone, string $templateName, array $bodyParams = [], string $language = 'en')
    {
        $phone = $this->normalizePhone($phone);

        if ($phone === '') {
            throw new \InvalidArgumentException('Phone is required');
        }

        $parameters = [];
        foreach ($bodyParams as $value) {
            // Template variables cannot be empty and have limited length
            $text = trim((string) $value);
            if ($text === '') {
                $text = '-';
            }
            $parameters[] = [
                'type' => 'text',
                'text' => mb_substr($text, 0, 500),
            ];
        }

        $template = [
            'name' => $templateName,
            'language' => ['code' => $language],
        ];

        if (!empty($parameters)) {
            $template['components'] = [
                [
                    'type' => 'body',
                    'parameters' => $parameters,
                ],
            ];
        }

        $response = Http::withToken($this->token())
            ->post($this->apiUrl(), [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => $template,
            ]);

        Log::info('WhatsApp template response', [
            'template' => $templateName,
            'status' => $response->status(),
            'body' => $response->json(),
            'to' => $phone,
        ]);

        return $response;
    }

    /**
     * Admin reminder / offer:
     * 1) If WHATSAPP_REMINDER_TEMPLATE is set → send template (reliable).
     * 2) Else fall back to free-form text (24h window only).
     *
     * @return array{ok:bool,mode:string,response:\Illuminate\Http\Client\Response|null,message:string}
     */
    public function sendCustomerOutreach(?string $phone, string $customerName, string $message): array
    {
        $template = trim((string) env('WHATSAPP_REMINDER_TEMPLATE', ''));
        $language = trim((string) env('WHATSAPP_REMINDER_TEMPLATE_LANG', 'en')) ?: 'en';

        // Collapse whitespace for template vars
        $shortMessage = trim(preg_replace('/\s+/', ' ', $message) ?? $message);

        if ($template !== '') {
            $response = $this->sendTemplateMessage(
                $phone,
                $template,
                [$customerName ?: 'Customer', $shortMessage],
                $language
            );

            $ok = $response->successful() && empty(data_get($response->json(), 'error'));

            return [
                'ok' => $ok,
                'mode' => 'template',
                'response' => $response,
                'message' => $ok
                    ? 'WhatsApp template "' . $template . '" queued to ' . $this->normalizePhone($phone)
                    : ('WhatsApp template failed: ' . (data_get($response->json(), 'error.message') ?: $response->body())),
            ];
        }

        $response = $this->sendTextMessage($phone, $message);
        $ok = $response->successful() && empty(data_get($response->json(), 'error'));
        $wamid = data_get($response->json(), 'messages.0.id');

        return [
            'ok' => $ok,
            'mode' => 'text',
            'response' => $response,
            'message' => $ok
                ? ('WhatsApp accepted text (id: ' . ($wamid ?: 'n/a') . '). '
                    . 'Delivery only works if this customer messaged your business WhatsApp in the last 24 hours. '
                    . 'For offers/reminders, create a Meta template and set WHATSAPP_REMINDER_TEMPLATE in .env.')
                : ('WhatsApp text failed: ' . (data_get($response->json(), 'error.message') ?: $response->body())),
        ];
    }
}
