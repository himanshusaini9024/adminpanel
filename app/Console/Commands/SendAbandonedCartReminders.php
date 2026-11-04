<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\WhatsAppHeaderImageService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'cart:send-abandonment-reminders
                            {--dry-run : List eligible customers without sending}
                            {--hours= : Override the within-hours window (default from config)}';

    protected $description = 'Send WhatsApp reminders for carts updated within the last 24 hours (intended to run daily at 21:00)';

    public function handle(WhatsAppService $whatsapp, WhatsAppHeaderImageService $headerImages): int
    {
        $hours = (int) ($this->option('hours') ?: config('services.whatsapp.cart_reminder_within_hours', 24));
        $hours = max(1, $hours);
        $since = now()->subHours($hours);
        $dryRun = (bool) $this->option('dry-run');
        $message = (string) config(
            'services.whatsapp.cart_reminder_message',
            'You left something lovely in your cart. Complete your order on dhirago.com before it sells out.'
        );
        $fallbackHeader = (string) config('services.whatsapp.reminder_template_image', '');

        $customers = Customer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('cart_updated_at')
            ->where('cart_updated_at', '>=', $since)
            ->where(function ($q) {
                $q->whereNull('cart_reminder_sent_at')
                    ->orWhereColumn('cart_reminder_sent_at', '<', 'cart_updated_at');
            })
            ->whereNotNull('cart')
            ->where('cart', '!=', '')
            ->where('cart', '!=', '[]')
            ->where('cart', '!=', 'null')
            ->orderBy('customer_id')
            ->get(['customer_id', 'first_name', 'last_name', 'phone', 'cart', 'cart_updated_at', 'cart_reminder_sent_at']);

        $eligible = $customers->filter(fn (Customer $c) => $c->hasCartItems())->values();

        $this->info(sprintf(
            'Abandoned cart crawl: %d eligible (within last %d hours)%s',
            $eligible->count(),
            $hours,
            $dryRun ? ' [DRY RUN]' : ''
        ));

        Log::info('cart:send-abandonment-reminders start', [
            'eligible' => $eligible->count(),
            'hours' => $hours,
            'dry_run' => $dryRun,
        ]);

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($eligible as $index => $customer) {
            $name = $customer->full_name;
            $productHint = $this->firstProductName($customer);
            $headerImage = $this->resolveCartHeaderImage($customer, $headerImages, $fallbackHeader);

            $this->line(sprintf(
                '#%d %s (%s) cart@%s — %s',
                $customer->customer_id,
                $name,
                $customer->phone,
                optional($customer->cart_updated_at)->toDateTimeString(),
                $productHint ?: 'item(s) in cart'
            ));
            $this->line('  header: ' . ($headerImage ?: '(none)'));

            if ($dryRun) {
                $skipped++;
                continue;
            }

            $body = $message;
            if ($productHint !== '') {
                $body = "Your {$productHint} is still waiting in the cart. " . $message;
            }

            try {
                $result = $whatsapp->sendCustomerOutreach(
                    $customer->phone,
                    $name,
                    $body,
                    $headerImage ?: null
                );

                if (!empty($result['ok'])) {
                    $customer->cart_reminder_sent_at = now();
                    $customer->save();
                    $sent++;
                } else {
                    $failed++;
                    Log::warning('Abandoned cart WhatsApp failed', [
                        'customer_id' => $customer->customer_id,
                        'message' => $result['message'] ?? null,
                        'header_image' => $headerImage,
                    ]);
                    $this->warn('  failed: ' . ($result['message'] ?? 'unknown'));
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Abandoned cart WhatsApp exception', [
                    'customer_id' => $customer->customer_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error('  error: ' . $e->getMessage());
            }

            if ($index < $eligible->count() - 1) {
                usleep(250000);
            }
        }

        $summary = compact('sent', 'failed', 'skipped') + ['eligible' => $eligible->count()];
        Log::info('cart:send-abandonment-reminders done', $summary);
        $this->info("Done. sent={$sent} failed={$failed} dry_skipped={$skipped}");

        return self::SUCCESS;
    }

    private function firstProductName(Customer $customer): string
    {
        $first = $this->firstCartItem($customer);
        if (!$first) {
            return '';
        }

        return trim((string) ($first['name'] ?? $first['title'] ?? ''));
    }

    /**
     * Prefer the first cart product image as WhatsApp header.
     * WebP is converted to a public JPEG (Meta delivers those more reliably).
     */
    private function resolveCartHeaderImage(
        Customer $customer,
        WhatsAppHeaderImageService $headerImages,
        string $fallback
    ): string {
        $raw = $this->firstCartImagePath($customer);
        if ($raw === '') {
            return $fallback;
        }

        $absolute = function_exists('media_url') ? media_url($raw) : $raw;
        if ($absolute === '' || (!str_starts_with($absolute, 'http://') && !str_starts_with($absolute, 'https://'))) {
            return $fallback;
        }

        // Drop cache-buster for cleaner conversion cache key
        $absolute = strtok($absolute, '?') ?: $absolute;

        $jpeg = $headerImages->ensureJpegUrl($absolute);
        if ($jpeg) {
            return $jpeg;
        }

        Log::warning('Abandoned cart: product header convert failed, using fallback', [
            'customer_id' => $customer->customer_id,
            'source' => $absolute,
        ]);

        return $fallback;
    }

    private function firstCartImagePath(Customer $customer): string
    {
        $first = $this->firstCartItem($customer);
        if (!$first) {
            return '';
        }

        $candidates = [
            $first['thumb']['url'] ?? null,
            $first['image'][0]['url'] ?? null,
            $first['images'][0]['url'] ?? null,
            $first['photo'] ?? null,
            $first['thumbnail'] ?? null,
            $first['url'] ?? null,
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && trim($path) !== '') {
                return trim($path);
            }
        }

        return '';
    }

    private function firstCartItem(Customer $customer): ?array
    {
        $items = $customer->cart_items;
        $first = $items[0] ?? null;

        return is_array($first) ? $first : null;
    }
}
