<?php

namespace App\Jobs;

use App\Models\ReturnOrder;
use App\Services\WhatsAppService;
use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notify customer by email + WhatsApp + Web Push on return/exchange status changes.
 *
 * Events mirror return statuses:
 * pending | rejected | pickup_scheduled | pickup_failed | picked_up |
 * in_transit | delivered | refunded | replacement_created
 */
class ReturnStatusNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $returnId,
        public string $event
    ) {}

    public function handle(WhatsAppService $whatsapp, WebPushService $webPush): void
    {
        $return = ReturnOrder::with(['order', 'orderItem', 'replacementOrder'])->find($this->returnId);
        if (!$return || !$return->order) {
            Log::warning('ReturnStatusNotificationJob: return/order not found', [
                'return_id' => $this->returnId,
            ]);
            return;
        }

        $config = $this->eventConfig($return);
        if (!$config) {
            Log::warning('ReturnStatusNotificationJob: unknown event', [
                'return_id' => $this->returnId,
                'event' => $this->event,
            ]);
            return;
        }

        Log::info('ReturnStatusNotificationJob start', [
            'return_id' => $return->id,
            'order_number' => $return->order_number,
            'type' => $return->type,
            'event' => $this->event,
        ]);

        $this->sendMail($return, $config);
        $this->sendWhatsApp($return, $config, $whatsapp);
        $this->sendWebPush($return, $config, $webPush);
    }

    /**
     * @return array{subject:string,headline:string,body:string,wa_body:string}|null
     */
    private function eventConfig(ReturnOrder $return): ?array
    {
        $order = $return->order;
        $orderNo = (string) ($return->order_number ?: $order->order_number);
        $isExchange = $return->type === 'exchange';
        $label = $isExchange ? 'exchange' : 'return';
        $Label = $isExchange ? 'Exchange' : 'Return';
        $itemName = $return->orderItem->name ?? 'your item';
        $courier = $return->courier ?: 'our courier partner';
        $replacementNo = $return->replacementOrder->order_number ?? null;
        $refundAmount = $return->refund_amount
            ? '₹' . number_format((float) $return->refund_amount, 2)
            : null;

        return match ($this->event) {
            'pending' => [
                'subject' => "{$Label} request received for order #{$orderNo}",
                'headline' => "{$Label} request received",
                'body' => "We have received your {$label} request for <strong>{$itemName}</strong> on order <strong>#{$orderNo}</strong>. Our team will review it shortly.",
                'wa_body' => "We received your {$label} request for {$itemName} on order #{$orderNo}. Our team will review it shortly.",
            ],
            'rejected' => [
                'subject' => "{$Label} request update for order #{$orderNo}",
                'headline' => "{$Label} request declined",
                'body' => "Your {$label} request for order <strong>#{$orderNo}</strong> could not be approved. If you have questions, reply to this email or chat with us on WhatsApp.",
                'wa_body' => "Your {$label} request for order #{$orderNo} could not be approved. Contact us if you need help.",
            ],
            'pickup_scheduled' => [
                'subject' => "Pickup scheduled for your {$label} — order #{$orderNo}",
                'headline' => 'Pickup scheduled',
                'body' => "Your {$label} pickup for <strong>{$itemName}</strong> (order <strong>#{$orderNo}</strong>) has been scheduled with {$courier}. Please keep the item ready and your phone reachable.",
                'wa_body' => "Pickup for your {$label} ({$itemName}, order #{$orderNo}) is scheduled with {$courier}. Please keep the item ready.",
            ],
            'pickup_failed' => [
                'subject' => "Pickup could not be scheduled — order #{$orderNo}",
                'headline' => 'Pickup scheduling failed',
                'body' => "We could not schedule courier pickup for your {$label} on order <strong>#{$orderNo}</strong>. Our team will retry or contact you soon.",
                'wa_body' => "We could not schedule pickup for your {$label} on order #{$orderNo}. Our team will follow up soon.",
            ],
            'picked_up' => [
                'subject' => "Item picked up for your {$label} — order #{$orderNo}",
                'headline' => 'Item picked up',
                'body' => "Your item <strong>{$itemName}</strong> for order <strong>#{$orderNo}</strong> has been picked up by {$courier} and is on its way to our warehouse.",
                'wa_body' => "Your {$label} item ({$itemName}) for order #{$orderNo} has been picked up by {$courier}.",
            ],
            'in_transit' => [
                'subject' => "Your {$label} is in transit — order #{$orderNo}",
                'headline' => 'In transit to warehouse',
                'body' => "Your {$label} shipment for order <strong>#{$orderNo}</strong> is in transit to our warehouse. We will notify you when it arrives.",
                'wa_body' => "Your {$label} for order #{$orderNo} is in transit to our warehouse.",
            ],
            'delivered' => [
                'subject' => "{$Label} received at warehouse — order #{$orderNo}",
                'headline' => 'Received at warehouse',
                'body' => $isExchange
                    ? "We have received your exchanged item for order <strong>#{$orderNo}</strong>. We will create your replacement order next."
                    : "We have received your returned item for order <strong>#{$orderNo}</strong>. Your refund will be processed shortly.",
                'wa_body' => $isExchange
                    ? "We received your exchange item for order #{$orderNo}. Replacement order coming next."
                    : "We received your return for order #{$orderNo}. Refund will be processed shortly.",
            ],
            'refunded' => [
                'subject' => "Refund processed for order #{$orderNo}",
                'headline' => 'Refund processed',
                'body' => 'Your refund'
                    . ($refundAmount ? " of <strong>{$refundAmount}</strong>" : '')
                    . " for order <strong>#{$orderNo}</strong> has been processed. It may take a few business days to reflect in your account.",
                'wa_body' => 'Your refund'
                    . ($refundAmount ? " of {$refundAmount}" : '')
                    . " for order #{$orderNo} has been processed.",
            ],
            'replacement_created' => [
                'subject' => 'Replacement order created'
                    . ($replacementNo ? " #{$replacementNo}" : '')
                    . " for exchange #{$orderNo}",
                'headline' => 'Replacement order created',
                'body' => 'Your exchange for order <strong>#' . $orderNo . '</strong> is confirmed.'
                    . ($replacementNo
                        ? " Replacement order <strong>#{$replacementNo}</strong> has been created and will ship soon."
                        : ' Your replacement order has been created and will ship soon.'),
                'wa_body' => 'Your exchange for order #' . $orderNo . ' is confirmed.'
                    . ($replacementNo
                        ? " Replacement order #{$replacementNo} has been created and will ship soon."
                        : ' Your replacement order has been created and will ship soon.'),
            ],
            default => null,
        };
    }

    private function sendMail(ReturnOrder $return, array $config): void
    {
        $order = $return->order;
        if (empty($order->email)) {
            Log::info('ReturnStatusNotificationJob: no email, skip mail', [
                'return_id' => $return->id,
                'event' => $this->event,
            ]);
            return;
        }

        try {
            $html = view('emails.return-status', [
                'order' => $order,
                'return' => $return,
                'subject' => $config['subject'],
                'headline' => $config['headline'],
                'bodyHtml' => $config['body'],
                'event' => $this->event,
            ])->render();

            Mail::html($html, function ($message) use ($order, $config) {
                $message->to($order->email)->subject($config['subject']);
            });

            Log::info('ReturnStatusNotificationJob: mail sent', [
                'return_id' => $return->id,
                'event' => $this->event,
                'to' => $order->email,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReturnStatusNotificationJob: mail failed', [
                'return_id' => $return->id,
                'event' => $this->event,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function sendWhatsApp(ReturnOrder $return, array $config, WhatsAppService $whatsapp): void
    {
        $order = $return->order;
        if (empty($order->phone)) {
            Log::info('ReturnStatusNotificationJob: no phone, skip WhatsApp', [
                'return_id' => $return->id,
                'event' => $this->event,
            ]);
            return;
        }

        try {
            $name = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
            // Uses approved outreach template when configured (works outside 24h window).
            $result = $whatsapp->sendCustomerOutreach(
                $order->phone,
                $name,
                $config['wa_body'] . "\n\nThanks for shopping with Dhirago."
            );

            Log::info('ReturnStatusNotificationJob: WhatsApp', [
                'return_id' => $return->id,
                'event' => $this->event,
                'ok' => $result['ok'] ?? false,
                'mode' => $result['mode'] ?? null,
                'message' => $result['message'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReturnStatusNotificationJob: WhatsApp failed', [
                'return_id' => $return->id,
                'event' => $this->event,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function sendWebPush(ReturnOrder $return, array $config, WebPushService $webPush): void
    {
        $order = $return->order;
        if (empty($order->customer_id)) {
            return;
        }

        try {
            $summary = $webPush->sendToCustomer(
                (int) $order->customer_id,
                [
                    'title' => 'Dhirago',
                    'body' => strip_tags($config['headline'] . ' — order #' . ($return->order_number ?: $order->order_number)),
                    'url' => env('ORDER_TRACK_URL', 'https://dhirago.com/return/track-order'),
                    'data' => [
                        'order_number' => (string) ($return->order_number ?: $order->order_number),
                        'return_id' => (string) $return->id,
                        'event' => $this->event,
                        'type' => (string) $return->type,
                    ],
                ]
            );

            Log::info('ReturnStatusNotificationJob: web push', [
                'return_id' => $return->id,
                'event' => $this->event,
                'sent' => $summary['sent'] ?? 0,
                'failed' => $summary['failed'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReturnStatusNotificationJob: web push failed', [
                'return_id' => $return->id,
                'event' => $this->event,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
