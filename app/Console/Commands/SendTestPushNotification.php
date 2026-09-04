<?php

namespace App\Console\Commands;

use App\Models\PushToken;
use App\Services\WebPushService;
use Illuminate\Console\Command;

class SendTestPushNotification extends Command
{
    protected $signature = 'push:test
        {--endpoint= : Send to a specific push endpoint}
        {--customer= : Send to all active subscriptions for a customer id}
        {--all : Send to every active subscription}
        {--title=Dhirago : Notification title}
        {--body=Test web push from Dhirago : Notification body}
        {--image= : Large banner image URL}
        {--icon= : Small icon URL}
        {--url= : Click URL (defaults to STOREFRONT_URL)}';

    protected $description = 'Send a test native Web Push notification (VAPID)';

    public function handle(WebPushService $push): int
    {
        $options = [
            'title' => (string) $this->option('title'),
            'body' => (string) $this->option('body'),
            'url' => $this->option('url') ?: config('services.webpush.storefront_url', 'https://dhirago.com'),
            'image' => $this->option('image') ?: null,
            'icon' => $this->option('icon') ?: ($this->option('image') ?: null),
        ];

        if ($endpoint = $this->option('endpoint')) {
            $row = PushToken::where('token', $endpoint)->where('is_active', true)->first();
            if (!$row || !$row->p256dh || !$row->auth) {
                $this->error('No active subscription found for that endpoint (need p256dh/auth).');
                return self::FAILURE;
            }

            $result = $push->sendToSubscription(
                $row->token,
                $row->p256dh,
                $row->auth,
                $options
            );
            $this->line($result['ok'] ? 'Sent.' : ('Failed: ' . $result['message']));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('all')) {
            $summary = $push->sendToCustomer(null, $options);
            $this->info("Sent: {$summary['sent']}, Failed: {$summary['failed']}");
            foreach ($summary['errors'] as $error) {
                $this->warn($error);
            }

            return $summary['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        }

        if ($customerId = $this->option('customer')) {
            $summary = $push->sendToCustomer((int) $customerId, $options);
            $this->info("Sent: {$summary['sent']}, Failed: {$summary['failed']}");
            foreach ($summary['errors'] as $error) {
                $this->warn($error);
            }

            return $summary['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        }

        $count = PushToken::where('is_active', true)->whereNotNull('p256dh')->count();
        $this->warn('No target selected. Active Web Push subscriptions: ' . $count);
        $this->line('Examples:');
        $this->line('  php artisan push:test --all --title="Aprons" --body="At Rs. 121!" --image="https://images.dhirago.com/.../banner.webp"');
        $this->line('  php artisan push:test --customer=12');

        return self::INVALID;
    }
}
