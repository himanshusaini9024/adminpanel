<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class FirstOrderDiscountService
{
    public const PERCENT = 10;

    public const LABEL = 'First order 10% off';

    /**
     * Toggle via .env: FIRST_ORDER_DISCOUNT_ENABLED=true
     */
    public function isEnabled(): bool
    {
        return filter_var(env('FIRST_ORDER_DISCOUNT_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Eligible when enabled and the customer has never placed a normal order.
     */
    public function isEligible(?int $customerId = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $customerId = $customerId ?? Auth::guard('customer')->id();

        if (!$customerId) {
            return false;
        }

        return !$this->hasPriorNormalOrder($customerId);
    }

    public function hasPriorNormalOrder(int $customerId): bool
    {
        return Order::where('customer_id', $customerId)
            ->where(function ($q) {
                $q->whereNull('order_type')->orWhere('order_type', 'normal');
            })
            ->exists();
    }

    public function isEligibleLocked(int $customerId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        Customer::where('customer_id', $customerId)->lockForUpdate()->first();

        return !$this->hasPriorNormalOrder($customerId);
    }

    public function subtotalFromItems(array $items): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $price = (float) ($item['price'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            $subtotal += $price * $qty;
        }

        return round($subtotal, 2);
    }

    public function quote(float $subTotal, ?int $customerId = null, bool $apply = true): array
    {
        $subTotal = round(max(0, $subTotal), 2);
        $eligible = $apply && $this->isEligible($customerId);
        $discount = 0.0;

        if ($eligible && $subTotal > 0) {
            $discount = round(($subTotal * self::PERCENT) / 100, 2);
        }

        return [
            'eligible'  => $eligible,
            'percent'   => self::PERCENT,
            'label'     => $eligible ? self::LABEL : null,
            'sub_total' => $subTotal,
            'discount'  => $discount,
            'total'     => round(max(0, $subTotal - $discount), 2),
            'enabled'   => $this->isEnabled(),
        ];
    }

    public function applyForOrder(
        float $subTotal,
        ?int $customerId,
        bool $skip = false
    ): array {
        $subTotal = round(max(0, $subTotal), 2);
        $discount = 0.0;
        $applied = false;

        if (!$skip && $customerId && $this->isEligibleLocked($customerId) && $subTotal > 0) {
            $discount = round(($subTotal * self::PERCENT) / 100, 2);
            $applied = $discount > 0;
        }

        return [
            'sub_total' => $subTotal,
            'discount'  => $discount,
            'total'     => round(max(0, $subTotal - $discount), 2),
            'applied'   => $applied,
        ];
    }
}
