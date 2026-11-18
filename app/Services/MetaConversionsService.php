<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Conversions API (server-side events). Customer data is normalised and SHA-256 hashed
 * as Meta requires; the browser Pixel sends the same event_id so Meta counts each event once.
 *
 * Local / private event_source_url isolation: do not write into the live dataset unless
 * META_TEST_EVENT_CODE is set (events then appear under Test events only).
 */
class MetaConversionsService
{
    public function enabled(): bool
    {
        return (bool) (config('services.meta.pixel_id') && config('services.meta.access_token'));
    }

    /**
     * Must match the eventID the storefront Pixel uses for the same order.
     */
    public static function purchaseEventId(Order $order): string
    {
        return 'purchase_' . $order->order_number;
    }

    /**
     * @param array{ip?:?string,user_agent?:?string,fbp?:?string,fbc?:?string,event_source_url?:?string} $context
     */
    public function sendPurchase(Order $order, array $context = []): void
    {
        $order->loadMissing('items');

        $contents = $order->items->map(fn ($item) => [
            'id' => (string) $item->product_id,
            'quantity' => (int) $item->quantity,
            'item_price' => (float) $item->price,
        ])->values()->all();

        $eventSourceUrl = $context['event_source_url']
            ?? rtrim((string) config('services.webpush.storefront_url'), '/') . '/checkout';

        $this->send([[
            'event_name' => 'Purchase',
            'event_time' => ($order->created_at ?? now())->timestamp,
            'event_id' => self::purchaseEventId($order),
            'action_source' => 'website',
            'event_source_url' => $eventSourceUrl,
            'user_data' => $this->userData($order, $context),
            'custom_data' => [
                'currency' => 'INR',
                'value' => round((float) $order->total_amount, 2),
                'order_id' => (string) $order->order_number,
                'content_type' => 'product',
                'contents' => $contents,
                'num_items' => array_sum(array_column($contents, 'quantity')),
            ],
        ]], ['order_id' => $order->id, 'event_source_url' => $eventSourceUrl]);
    }

    public function send(array $events, array $logContext = []): bool
    {
        if (!$this->enabled()) {
            Log::info('Meta CAPI skipped: META_PIXEL_ID / META_ACCESS_TOKEN not set', $logContext);

            return false;
        }

        $eventSourceUrl = $logContext['event_source_url']
            ?? ($events[0]['event_source_url'] ?? null);

        $gate = $this->resolveSendGate($eventSourceUrl);
        if (!$gate['ok']) {
            Log::info('Meta CAPI skipped: ' . $gate['reason'], $logContext + [
                'event_source_url' => $eventSourceUrl,
            ]);

            return false;
        }

        $body = ['data' => $events, 'access_token' => config('services.meta.access_token')];
        if (!empty($gate['test_event_code'])) {
            $body['test_event_code'] = $gate['test_event_code'];
        }

        try {
            $response = Http::timeout(10)->asJson()->post(sprintf(
                'https://graph.facebook.com/%s/%s/events',
                config('services.meta.graph_version'),
                config('services.meta.pixel_id')
            ), $body);

            if ($response->failed()) {
                Log::warning('Meta CAPI request failed', $logContext + [
                    'status' => $response->status(),
                    'error' => $response->json('error.message') ?? $response->body(),
                    'test_mode' => !empty($gate['test_event_code']),
                ]);

                return false;
            }

            Log::info('Meta CAPI sent', $logContext + [
                'events' => array_column($events, 'event_name'),
                'events_received' => $response->json('events_received'),
                'test_mode' => !empty($gate['test_event_code']),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Meta CAPI error', $logContext + ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array{ok:bool,reason?:string,test_event_code?:?string}
     */
    private function resolveSendGate(?string $eventSourceUrl): array
    {
        if (config('services.meta.force_production')) {
            return ['ok' => true, 'test_event_code' => null];
        }

        $privateSource = $this->isPrivateUrl($eventSourceUrl);
        $appNonProd = config('services.meta.force_non_production') || $this->isAppNonProduction();

        // Local storefront → production API must never write into the live dataset.
        if (!$appNonProd && $privateSource) {
            return [
                'ok' => false,
                'reason' => 'private_event_source_blocked_on_production_api',
            ];
        }

        if ($appNonProd || $privateSource) {
            $testCode = trim((string) config('services.meta.test_event_code'));
            if ($testCode === '') {
                return [
                    'ok' => false,
                    'reason' => 'non_production_requires_META_TEST_EVENT_CODE',
                ];
            }

            return ['ok' => true, 'test_event_code' => $testCode];
        }

        // Production traffic: never attach a leftover META_TEST_EVENT_CODE.
        return ['ok' => true, 'test_event_code' => null];
    }

    private function isAppNonProduction(): bool
    {
        if ($this->isPrivateUrl(config('app.url'))) {
            return true;
        }

        return app()->environment(['local', 'development', 'testing']);
    }

    private function isPrivateUrl(?string $value): bool
    {
        if (!$value) {
            return false;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $host = strtolower(rtrim($host, '.'));

        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        return false;
    }

    private function userData(Order $order, array $context): array
    {
        $phone = preg_replace('/\D+/', '', (string) $order->phone);
        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        $firstName = trim(explode(' ', trim((string) $order->first_name))[0] ?? '');

        return array_filter([
            'em' => self::hash($order->email ? strtolower(trim($order->email)) : null),
            'ph' => self::hash($phone ?: null),
            'fn' => self::hash($firstName !== '' ? mb_strtolower($firstName) : null),
            'ct' => self::hash($order->city ? preg_replace('/[^a-z]/', '', mb_strtolower($order->city)) : null),
            'zp' => self::hash($order->post_code ? preg_replace('/\s+/', '', (string) $order->post_code) : null),
            'country' => self::hash('in'),
            'external_id' => self::hash($order->customer_id ? (string) $order->customer_id : null),
            'client_ip_address' => $context['ip'] ?? null,
            'client_user_agent' => $context['user_agent'] ?? null,
            'fbp' => $context['fbp'] ?? null,
            'fbc' => $context['fbc'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private static function hash(?string $value): ?string
    {
        return $value === null || $value === '' ? null : hash('sha256', $value);
    }
}
