<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Abandoned-cart WhatsApp: once a night at 9 PM IST.
        // Only customers whose cart changed in the last 24 hours (and not already reminded).
        $schedule->command('cart:send-abandonment-reminders')
            ->dailyAt('21:00')
            ->timezone(config('app.timezone', 'Asia/Kolkata'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cart-reminders.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
