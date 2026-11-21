<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Fast2SMSService
{
    protected string $apiKey;
    protected string $senderId;
    protected string $entityId;
    protected string $templateId;
    protected string $route;
    protected string $templateText;

    public function __construct()
    {
        $this->apiKey = (string) config('services.fast2sms.api_key', '');
        $this->senderId = (string) config('services.fast2sms.sender_id', 'DHRAGO');
        $this->entityId = (string) config('services.fast2sms.entity_id', '');
        $this->templateId = (string) config('services.fast2sms.template_id', '');
        $this->route = (string) config('services.fast2sms.route', 'dlt');
        $this->templateText = (string) config(
            'services.fast2sms.template_text',
            'Your OTP for login to DHIRAGO FASHION is {#num#}. This OTP is valid for 10 minutes. Do not share this OTP with anyone. -DHIRAGO FASHION PRIVATE LIMITED'
        );
    }

    public function sendOtp(string $number, string $otp)
    {
        if ($this->apiKey === '') {
            Log::error('Fast2SMS OTP failed', [
                'reason' => 'empty_api_key',
                'hint' => 'Set FAST2SMS_API_KEY in .env then: php artisan config:clear && php artisan config:cache',
            ]);

            return new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(
                    401,
                    ['Content-Type' => 'application/json'],
                    json_encode([
                        'return' => false,
                        'status_code' => 412,
                        'message' => 'Fast2SMS API key not configured',
                    ])
                )
            );
        }

        $number = preg_replace('/\D+/', '', $number);
        if (strlen($number) === 12 && str_starts_with($number, '91')) {
            $number = substr($number, 2);
        }

        $payload = $this->route === 'dlt_manual'
            ? $this->buildDltManualPayload($number, $otp)
            : $this->buildDltPayload($number, $otp);

        $response = Http::withHeaders([
            'authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://www.fast2sms.com/dev/bulkV2', $payload);

        $body = $response->json();
        $accepted = $response->successful() && data_get($body, 'return') === true;

        if (!$accepted) {
            Log::error('Fast2SMS OTP failed', [
                'route' => $this->route,
                'status' => $response->status(),
                'body' => $body,
                'number' => $number,
                'api_key_set' => $this->apiKey !== '',
                'api_key_len' => strlen($this->apiKey),
            ]);
        } else {
            Log::info('Fast2SMS OTP queued', [
                'route' => $this->route,
                'request_id' => data_get($body, 'request_id'),
                'number' => $number,
            ]);
        }

        return $response;
    }

    /**
     * DLT route — template must be added in Fast2SMS DLT Manager.
     * message = DLT template ID, variables_values = OTP value for {#num#}
     */
    private function buildDltPayload(string $number, string $otp): array
    {
        return [
            'route' => 'dlt',
            'sender_id' => $this->senderId,
            'message' => $this->templateId,
            'variables_values' => $otp,
            'numbers' => $number,
        ];
    }

    /**
     * DLT manual — message text must match vilpower template EXACTLY (character-for-character).
     */
    private function buildDltManualPayload(string $number, string $otp): array
    {
        $message = str_replace('{#num#}', $otp, $this->templateText);

        $payload = [
            'route' => 'dlt_manual',
            'sender_id' => $this->senderId,
            'message' => $message,
            'template_id' => $this->templateId,
            'numbers' => $number,
        ];

        if ($this->entityId !== '') {
            $payload['entity_id'] = $this->entityId;
        }

        return $payload;
    }
}
