<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\WhatsAppService;
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

    public function handle(WhatsAppService $whatsapp)
    {
        $order = Order::with('items')->findOrFail($this->orderId);
           Log::info('storeorder', [
    'order' => $order->toArray(),
]);
        Log::info('WhatsApp order', [
            'order' => $order,
    'id' => $order->id,
    'order_number' => $order->order_number,
]);

        // Send mail
        try {
            Mail::send([], [], function ($message) use ($order) {

                $html = view('emails.order-confirmation', [
                    'order' => $order,
                ])->render();

                $message->to($order->email)
                    ->subject('Your Order Has Been Placed Successfully')
                    ->html($html);
            });
        } catch (\Exception $e) {

            Log::error('Mail Error', [
                'message' => $e->getMessage()
            ]);
        }

        // WhatsApp
        try {
            $phone = '91' . ltrim($order->phone, '0');

            $whatsapp->sendOrderConfirmation(
                $phone,
                $order->first_name,
                $order->order_number,
                $order->total_amount
            );
        } catch (\Exception $e) {

            Log::error('WhatsApp Error', [
                'message' => $e->getMessage()
            ]);
        }
    }
}
