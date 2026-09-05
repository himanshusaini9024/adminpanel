<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        // Meta dashboard verify token (falls back to WHATSAPP_TOKEN for legacy setups)
        $verifyToken = (string) config('services.whatsapp.webhook_verify_token', '');

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode === 'subscribe' && $verifyToken !== '' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified');

            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken,
        ]);

        return response('Verification failed', 403);
    }

    public function handle(Request $request)
    {
        $payload = $request->all();

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value = data_get($change, 'value', []);

                foreach (data_get($value, 'statuses', []) as $status) {
                    $this->logMessageStatus($status);
                }

                foreach (data_get($value, 'messages', []) as $message) {
                    Log::info('WhatsApp inbound message', [
                        'from' => data_get($message, 'from'),
                        'type' => data_get($message, 'type'),
                        'id' => data_get($message, 'id'),
                    ]);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    private function logMessageStatus(array $status): void
    {
        $state = (string) data_get($status, 'status', 'unknown');
        $wamid = (string) data_get($status, 'id', '');
        $recipient = (string) data_get($status, 'recipient_id', '');
        $errors = data_get($status, 'errors', []);

        $context = [
            'wamid' => $wamid,
            'status' => $state,
            'recipient' => $recipient,
            'timestamp' => data_get($status, 'timestamp'),
            'conversation' => data_get($status, 'conversation'),
            'pricing' => data_get($status, 'pricing'),
            'errors' => $errors,
        ];

        if ($state === 'failed') {
            Log::error('WhatsApp message delivery FAILED', $context);
            return;
        }

        if (in_array($state, ['delivered', 'read', 'sent'], true)) {
            Log::info('WhatsApp message ' . $state, $context);
            return;
        }

        Log::info('WhatsApp message status update', $context);
    }
}
