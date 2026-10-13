<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\MetaConversionsService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Sends the Purchase event to Meta from the server once the order is saved, so it is counted
 * even if the customer's browser never reaches the success page.
 */
class MetaPurchaseJob
{
    use Dispatchable, Queueable;

    public function __construct(public int $orderId, public array $context = [])
    {
    }

    public function handle(MetaConversionsService $meta): void
    {
        $order = Order::find($this->orderId);
        if ($order) {
            $meta->sendPurchase($order, $this->context);
        }
    }
}
