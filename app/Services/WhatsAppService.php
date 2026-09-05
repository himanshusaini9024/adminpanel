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

    public function isConfigured(): bool
    {
        return $this->token() !== '' && $this->phoneId() !== '';
    }

    public function configError(): ?string
    {
        if ($this->token() === '') {
            return 'WhatsApp is not configured: set WHATSAPP_TOKEN in .env';
        }
        if ($this->phoneId() === '') {
            return 'WhatsApp is not configured: set WHATSAPP_PHONE_ID in .env';
        }

        return null;
    }

    protected function phoneId(): string
    {
        return trim((string) config('services.whatsapp.phone_id', ''));
    }

    protected function apiUrl(): string
    {
        $version = trim((string) config('services.whatsapp.api_version', 'v22.0')) ?: 'v22.0';

        return 'https://graph.facebook.com/' . $version . '/' . $this->phoneId() . '/messages';
    }

    protected function token(): string
    {
        return trim((string) config('services.whatsapp.token', ''));
    }

    public function sendOrderConfirmation($phone, $name, $orderNumber, $amount)
    {
        try {
            if ($error = $this->configError()) {
                throw new \RuntimeException($error);
            }

            $phone = $this->normalizePhone($phone);

            $response = Http::withToken($this->token())
                ->timeout(30)
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
        if ($error = $this->configError()) {
            throw new \RuntimeException($error);
        }

        $phone = $this->normalizePhone($phone);

        if ($phone === '' || trim($text) === '') {
            throw new \InvalidArgumentException('Phone and message are required');
        }

        // WhatsApp text body max ~4096; keep safe
        $text = mb_substr(trim($text), 0, 4000);

        try {
            $response = Http::withToken($this->token())
                ->timeout(30)
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
     *   Name: dhirago_customer_offer  (or set WHATSAPP_REMINDER_TEMPLATE)
     *   Language: en
     *   Header: Image (variable)
     *   Body: Hey {{1}}! {{2}} Thanks for joining us. - Dhirago
     *
     * @param  array<int, string>  $bodyParams  Values for {{1}}, {{2}}, ...
     * @param  string|null  $headerImageUrl  Public HTTPS image URL when template has image header
     */
    public function sendTemplateMessage(
        ?string $phone,
        string $templateName,
        array $bodyParams = [],
        string $language = 'en',
        ?string $headerImageUrl = null
    ) {
        if ($error = $this->configError()) {
            throw new \RuntimeException($error);
        }

        $phone = $this->normalizePhone($phone);

        if ($phone === '') {
            throw new \InvalidArgumentException('Phone is required');
        }

        $parameters = [];
        foreach ($bodyParams as $value) {
            // Template variables cannot be empty and have limited length / no newlines
            $text = trim(preg_replace('/\s+/', ' ', (string) $value) ?? (string) $value);
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

        $components = [];

        if ($headerImageUrl) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => [
                            'link' => $headerImageUrl,
                        ],
                    ],
                ],
            ];
        }

        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        if (!empty($components)) {
            $template['components'] = $components;
        }

        $response = Http::withToken($this->token())
            ->timeout(30)
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
            'header_image' => $headerImageUrl,
        ]);

        return $response;
    }

    /**
     * Prefer JPEG/PNG public URLs — Meta often accepts WebP then fails delivery.
     */
    public function resolveHeaderImage(?string $override = null): string
    {
        $url = trim((string) ($override ?: config('services.whatsapp.reminder_template_image', '')));

        if ($url === '') {
            return '';
        }

        // Soft-upgrade known WebP marketing banner to JPEG logo when still on default webp path
        if (str_ends_with(strtolower(parse_url($url, PHP_URL_PATH) ?: ''), '.webp')) {
            Log::warning('WhatsApp header image is WebP; Meta delivery is more reliable with JPEG/PNG', [
                'url' => $url,
            ]);
        }

        return $url;
    }

    /**
     * Admin reminder / offer:
     * 1) If reminder template is set → send template (reliable).
     * 2) Else fall back to free-form text (24h window only).
     *
     * @return array{ok:bool,mode:string,response:\Illuminate\Http\Client\Response|null,message:string}
     */
    public function sendCustomerOutreach(
        ?string $phone,
        string $customerName,
        string $message,
        ?string $headerImageOverride = null
    ): array {
        if ($error = $this->configError()) {
            return [
                'ok' => false,
                'mode' => 'config',
                'response' => null,
                'message' => $error,
            ];
        }

        $template = trim((string) config('services.whatsapp.reminder_template', ''));
        $language = trim((string) config('services.whatsapp.reminder_template_lang', 'en')) ?: 'en';
        $headerImage = $this->resolveHeaderImage($headerImageOverride);

        // Collapse whitespace for template vars
        $shortMessage = trim(preg_replace('/\s+/', ' ', $message) ?? $message);

        if ($template !== '') {
            if ($headerImage === '') {
                return [
                    'ok' => false,
                    'mode' => 'template',
                    'response' => null,
                    'message' => 'WhatsApp template requires a header image. Set WHATSAPP_REMINDER_TEMPLATE_IMAGE in .env to a public HTTPS JPEG/PNG URL.',
                ];
            }

            $response = $this->sendTemplateMessage(
                $phone,
                $template,
                [$customerName ?: 'Customer', $shortMessage],
                $language,
                $headerImage
            );

            $ok = $response->successful() && empty(data_get($response->json(), 'error'));
            $wamid = data_get($response->json(), 'messages.0.id');
            $msgStatus = data_get($response->json(), 'messages.0.message_status', 'accepted');

            return [
                'ok' => $ok,
                'mode' => 'template',
                'response' => $response,
                'message' => $ok
                    ? ('WhatsApp queued (' . $msgStatus . ') to ' . $this->normalizePhone($phone)
                        . ($wamid ? ' [id: ' . $wamid . ']' : '')
                        . '. If not received: check Updates tab; use JPEG/PNG header image; ensure app is Live or number is a test recipient.')
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
