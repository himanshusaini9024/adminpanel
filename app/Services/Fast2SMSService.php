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

    public function __construct()
    {
        $this->apiKey = (string) env('FAST2SMS_API_KEY', '');
        $this->senderId = (string) env('FAST2SMS_SENDER_ID', 'DHRAGO');
        $this->entityId = (string) env('FAST2SMS_ENTITY_ID', '');
        $this->templateId = (string) env('FAST2SMS_OTP_TEMPLATE_ID', '');
        $this->route = (string) env('FAST2SMS_ROUTE', 'dlt');
    }

    public function sendOtp(string $number, string $otp)
    {
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
        $message = str_replace(
            '{#num#}',
            $otp,
            (string) env(
                'FAST2SMS_OTP_TEMPLATE_TEXT',
                'Your OTP for login to DHIRAGO FASHION is {#num#}. This OTP is valid for 10 minutes. Do not share this OTP with anyone. -DHIRAGO FASHION PRIVATE LIMITED'
            )
        );

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
