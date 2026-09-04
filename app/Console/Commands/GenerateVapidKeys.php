<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Generate a VAPID key pair for native Web Push';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('Add these to adminpanel .env:');
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:hello@dhirago.com');
        $this->newLine();
        $this->info('And add this to dhirago .env.local:');
        $this->line('NEXT_PUBLIC_VAPID_PUBLIC_KEY=' . $keys['publicKey']);

        return self::SUCCESS;
    }
}
