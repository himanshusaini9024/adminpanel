<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\WhatsAppService;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderPlacedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(WhatsAppService $whatsapp, WebPushService $webPush): void
    {
        $order = Order::with(['items.product'])->find($this->orderId);
        if (!$order) {
            Log::warning('OrderPlacedJob: order not found', ['id' => $this->orderId]);
            return;
        }

        Log::info('OrderPlacedJob start', [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'email' => $order->email,
        ]);

        // Email
        if (!empty($order->email)) {
            try {
                $html = view('emails.order-confirmation', [
                    'order' => $order,
                ])->render();

                Mail::html($html, function ($message) use ($order) {
                    $message->to($order->email)
                        ->subject('Your Order Has Been Placed Successfully');
                });

                Log::info('OrderPlacedJob: mail sent', [
                    'order_id' => $order->id,
                    'to' => $order->email,
                ]);
            } catch (\Throwable $e) {
                Log::error('OrderPlacedJob: mail failed', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('OrderPlacedJob: no email on order', ['order_id' => $order->id]);
        }

        // WhatsApp
        if (!empty($order->phone)) {
            try {
                $whatsapp->sendOrderConfirmation(
                    $order->phone,
                    $order->first_name ?: 'Customer',
                    $order->order_number,
                    $order->total_amount
                );
            } catch (\Throwable $e) {
                Log::error('OrderPlacedJob: WhatsApp failed', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Browser Web Push
        if (!empty($order->customer_id)) {
            try {
                $summary = $webPush->sendToCustomer(
                    (int) $order->customer_id,
                    [
                        'title' => 'Order placed',
                        'body' => 'Your Dhirago order #' . $order->order_number . ' has been placed successfully.',
                        'url' => rtrim(config('services.webpush.storefront_url', 'https://dhirago.com'), '/') . '/account',
                        'data' => ['order_number' => (string) $order->order_number],
                    ]
                );

                Log::info('OrderPlacedJob: web push', [
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'sent' => $summary['sent'],
                    'failed' => $summary['failed'],
                ]);
            } catch (\Throwable $e) {
                Log::error('OrderPlacedJob: web push failed', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
