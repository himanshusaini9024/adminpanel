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

/**
 * Notify customer by email + WhatsApp when order shipping status changes.
 *
 * Events: shipment_booked | out_for_delivery | delivered
 */
class OrderStatusNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orderId,
        public string $event
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $order = Order::with(['items.product'])->find($this->orderId);
        if (!$order) {
            Log::warning('OrderStatusNotificationJob: order not found', ['id' => $this->orderId]);
            return;
        }

        $config = $this->eventConfig($order);
        if (!$config) {
            Log::warning('OrderStatusNotificationJob: unknown event', ['event' => $this->event]);
            return;
        }

        Log::info('OrderStatusNotificationJob start', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'event' => $this->event,
        ]);

        $this->sendMail($order, $config);
        $this->sendWhatsApp($order, $config, $whatsapp);
    }

    /**
     * WhatsApp template params must match Meta templates exactly:
     * - order_shipped: Hi {{1}}, ... order {{2}} ... Tracking {{3}} ... Track: {{4}}
     * - out_for_delivery: Hi {{1}}, your order #{{2}} is out for delivery
     * - order_delivered: Hi {{1}}, your order #{{2}} has been delivered. Thank you!
     *
     * @return array{subject:string,view:string,wa_template:string,wa_params:array}|null
     */
    private function eventConfig(Order $order): ?array
    {
        $name = $order->first_name ?: 'Customer';
        $orderNo = (string) $order->order_number;
        $orderLabel = env('ORDER_PREFIX', 'DF-') . $orderNo;
        $awb = $order->awb_code ?: 'N/A';
        $trackUrl = env('ORDER_TRACK_URL', 'https://dhirago.com/return/track-order');

        return match ($this->event) {
            'shipment_booked' => [
                'subject' => "Your order #{$orderNo} has been shipped",
                'view' => 'emails.order-shipment-booked',
                'wa_template' => env('WHATSAPP_SHIPMENT_BOOKED_TEMPLATE', 'order_shipped'),
                // Meta: {{1}} name, {{2}} order, {{3}} AWB, {{4}} track URL
                'wa_params' => [$name, $orderLabel, $awb, $trackUrl],
            ],
            'out_for_delivery' => [
                'subject' => "Your order #{$orderNo} is out for delivery",
                'view' => 'emails.order-out-for-delivery',
                'wa_template' => env('WHATSAPP_OUT_FOR_DELIVERY_TEMPLATE', 'out_for_delivery'),
                // Meta: {{1}} name, {{2}} order
                'wa_params' => [$name, $orderLabel],
            ],
            'delivered' => [
                'subject' => "Your order #{$orderNo} has been delivered",
                'view' => 'emails.order-delivered',
                'wa_template' => env('WHATSAPP_DELIVERED_TEMPLATE', 'order_delivered'),
                // Meta: {{1}} name, {{2}} order
                'wa_params' => [$name, $orderLabel],
            ],
            default => null,
        };
    }

    private function sendMail(Order $order, array $config): void
    {
        if (empty($order->email)) {
            Log::info('OrderStatusNotificationJob: no email, skip mail', [
                'order_id' => $order->id,
                'event' => $this->event,
            ]);
            return;
        }

        try {
            $html = view($config['view'], [
                'order' => $order,
                'event' => $this->event,
            ])->render();

            Mail::html($html, function ($message) use ($order, $config) {
                $message->to($order->email)
                    ->subject($config['subject']);
            });

            Log::info('OrderStatusNotificationJob: mail sent', [
                'order_id' => $order->id,
                'event' => $this->event,
                'to' => $order->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('OrderStatusNotificationJob: mail failed', [
                'order_id' => $order->id,
                'event' => $this->event,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function sendWhatsApp(Order $order, array $config, WhatsAppService $whatsapp): void
    {
        if (empty($order->phone)) {
            Log::info('OrderStatusNotificationJob: no phone, skip WhatsApp', [
                'order_id' => $order->id,
                'event' => $this->event,
            ]);
            return;
        }

        try {
            $lang = env('WHATSAPP_STATUS_TEMPLATE_LANG', 'en');
            $response = $whatsapp->sendTemplateMessage(
                $order->phone,
                $config['wa_template'],
                $config['wa_params'],
                $lang
            );

            $ok = $response->successful() && empty(data_get($response->json(), 'error'));

            // Template missing / not approved → fall back to free-form text
            // (works if customer got a WhatsApp from you in the last 24 hours)
            if (!$ok) {
                $code = (int) data_get($response->json(), 'error.code');
                $errMsg = (string) data_get($response->json(), 'error.message', 'unknown');

                Log::warning('OrderStatusNotificationJob: template failed, trying text fallback', [
                    'order_id' => $order->id,
                    'event' => $this->event,
                    'template' => $config['wa_template'],
                    'code' => $code,
                    'error' => $errMsg,
                ]);

                $text = $this->fallbackTextMessage($order);
                $textResponse = $whatsapp->sendTextMessage($order->phone, $text);

                Log::info('OrderStatusNotificationJob: WhatsApp text fallback', [
                    'order_id' => $order->id,
                    'event' => $this->event,
                    'status' => $textResponse->status(),
                    'body' => $textResponse->json(),
                ]);

                return;
            }

            Log::info('OrderStatusNotificationJob: WhatsApp response', [
                'order_id' => $order->id,
                'event' => $this->event,
                'template' => $config['wa_template'],
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::error('OrderStatusNotificationJob: WhatsApp failed', [
                'order_id' => $order->id,
                'event' => $this->event,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function fallbackTextMessage(Order $order): string
    {
        $name = $order->first_name ?: 'Customer';
        $orderNo = $order->order_number;
        $awb = $order->awb_code ?: 'N/A';
        $courier = $order->courier_name ?: 'our courier partner';

        return match ($this->event) {
            'shipment_booked' => "Hi {$name}, your Dhirago order #{$orderNo} has been shipped. Tracking/AWB: {$awb}. We'll update you when it's out for delivery.",
            'out_for_delivery' => "Hi {$name}, your Dhirago order #{$orderNo} is out for delivery via {$courier}. Please keep your phone reachable.",
            'delivered' => "Hi {$name}, your Dhirago order #{$orderNo} has been delivered. Thank you for shopping with us!",
            default => "Hi {$name}, your Dhirago order #{$orderNo} status has been updated.",
        };
    }
}
