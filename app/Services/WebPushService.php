<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Native Web Push (VAPID) — rich notifications with title, body, icon + large image.
 */
class WebPushService
{
    protected function client(): WebPush
    {
        $public = (string) config('services.webpush.public_key', '');
        $private = (string) config('services.webpush.private_key', '');
        $subject = (string) config('services.webpush.subject', 'mailto:contact@dhirago.com');

        if ($public === '' || $private === '') {
            throw new \RuntimeException(
                'Missing VAPID keys. Set VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY in .env (loaded via config/services.php).'
            );
        }

        // Pass a PSR logger so WebPush does not use trigger_error() —
        // Laravel converts E_USER_NOTICE into ErrorException and aborts the send.
        return new WebPush(
            [
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $public,
                    'privateKey' => $private,
                ],
            ],
            [],
            null,
            null,
            null,
            null,
            Log::channel()
        );
    }

    /**
     * @param  array{
     *   title:string,
     *   body:string,
     *   url?:string|null,
     *   image?:string|null,
     *   icon?:string|null,
     *   data?:array<string,mixed>
     * }  $options
     * @return array{ok:bool,message:string}
     */
    public function sendToSubscription(
        string $endpoint,
        string $p256dh,
        string $auth,
        array $options
    ): array {
        try {
            $site = rtrim((string) config('services.webpush.storefront_url', 'https://dhirago.com'), '/');
            $defaultIcon = $site . '/images/logo/logo.gif';

            $payload = array_filter([
                'title' => $options['title'] ?? 'Dhirago',
                'body' => $options['body'] ?? '',
                'icon' => $options['icon'] ?? $defaultIcon,
                'badge' => $defaultIcon,
                'image' => $options['image'] ?? null,
                'url' => $options['url'] ?? $site,
                'data' => $options['data'] ?? [],
            ], static fn ($v) => $v !== null && $v !== '');

            $subscription = Subscription::create([
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => $p256dh,
                    'auth' => $auth,
                ],
            ]);

            $webPush = $this->client();
            $report = $webPush->sendOneNotification(
                $subscription,
                json_encode($payload, JSON_UNESCAPED_SLASHES)
            );

            if ($report->isSuccess()) {
                PushToken::where('token', $endpoint)->update(['last_used_at' => now()]);

                return ['ok' => true, 'message' => 'Push sent.'];
            }

            $reason = $report->getReason();
            Log::warning('WebPush failed', [
                'endpoint' => substr($endpoint, 0, 80),
                'reason' => $reason,
            ]);

            if ($report->isSubscriptionExpired()) {
                PushToken::where('token', $endpoint)->update(['is_active' => false]);
            }

            return ['ok' => false, 'message' => $reason ?: 'Push failed'];
        } catch (\Throwable $e) {
            Log::error('WebPush exception', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array{
     *   title:string,
     *   body:string,
     *   url?:string|null,
     *   image?:string|null,
     *   icon?:string|null,
     *   data?:array<string,mixed>
     * }  $options
     * @return array{sent:int,failed:int,errors:array<int,string>}
     */
    public function sendToCustomer(?int $customerId, array $options): array
    {
        $query = PushToken::query()
            ->where('is_active', true)
            ->whereNotNull('p256dh')
            ->whereNotNull('auth');

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($query->get() as $row) {
            $result = $this->sendToSubscription(
                $row->token,
                $row->p256dh,
                $row->auth,
                $options
            );

            if ($result['ok']) {
                $sent++;
            } else {
                $failed++;
                $errors[] = $result['message'];
            }
        }

        return compact('sent', 'failed', 'errors');
    }
}
