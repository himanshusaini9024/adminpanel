<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Conversions API (server-side events). Customer data is normalised and SHA-256 hashed
 * as Meta requires; the browser Pixel sends the same event_id so Meta counts each event once.
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

        $this->send([[
            'event_name' => 'Purchase',
            'event_time' => ($order->created_at ?? now())->timestamp,
            'event_id' => self::purchaseEventId($order),
            'action_source' => 'website',
            'event_source_url' => $context['event_source_url'] ?? rtrim((string) config('services.webpush.storefront_url'), '/') . '/checkout',
            'user_data' => $this->userData($order, $context),
            'custom_data' => [
                'currency' => 'INR',
                'value' => round((float) $order->total_amount, 2),
                'order_id' => (string) $order->order_number,
                'content_type' => 'product',
                'contents' => $contents,
                'num_items' => array_sum(array_column($contents, 'quantity')),
            ],
        ]], ['order_id' => $order->id]);
    }

    public function send(array $events, array $logContext = []): bool
    {
        if (!$this->enabled()) {
            Log::info('Meta CAPI skipped: META_PIXEL_ID / META_ACCESS_TOKEN not set', $logContext);

            return false;
        }

        $body = ['data' => $events, 'access_token' => config('services.meta.access_token')];
        if ($testCode = config('services.meta.test_event_code')) {
            $body['test_event_code'] = $testCode;
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
                ]);

                return false;
            }

            Log::info('Meta CAPI sent', $logContext + [
                'events' => array_column($events, 'event_name'),
                'events_received' => $response->json('events_received'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Meta CAPI error', $logContext + ['message' => $e->getMessage()]);

            return false;
        }
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
