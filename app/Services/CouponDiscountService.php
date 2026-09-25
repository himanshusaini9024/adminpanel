<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use Illuminate\Support\Facades\Auth;

class CouponDiscountService
{
    public function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $code = strtoupper(trim($code));

        return $code === '' ? null : $code;
    }

    public function findActiveCoupon(?string $code): ?Coupon
    {
        $code = $this->normalizeCode($code);
        if (!$code) {
            return null;
        }

        $coupon = Coupon::where('code', $code)->where('status', 'active')->first();
        if (!$coupon) {
            return null;
        }

        if (!empty($coupon->expires_at) && now()->greaterThan($coupon->expires_at)) {
            return null;
        }

        return $coupon;
    }

    public function hasRedeemed(int $couponId, int $customerId): bool
    {
        return CouponRedemption::where('coupon_id', $couponId)
            ->where('customer_id', $customerId)
            ->exists();
    }

    public function discountAmount(Coupon $coupon, float $subTotal): float
    {
        $subTotal = round(max(0, $subTotal), 2);
        if ($subTotal <= 0) {
            return 0.0;
        }

        if ($coupon->type === 'fixed') {
            return round(min($subTotal, (float) $coupon->value), 2);
        }

        if ($coupon->type === 'percent') {
            return round(($subTotal * (float) $coupon->value) / 100, 2);
        }

        return 0.0;
    }

    /**
     * Quote a coupon without writing redemption.
     */
    public function quote(float $subTotal, ?string $code, ?int $customerId = null): array
    {
        $subTotal = round(max(0, $subTotal), 2);
        $customerId = $customerId ?? Auth::guard('customer')->id();
        $normalized = $this->normalizeCode($code);

        $empty = [
            'valid' => false,
            'code' => $normalized,
            'label' => null,
            'discount' => 0.0,
            'sub_total' => $subTotal,
            'total' => $subTotal,
            'message' => null,
            'coupon_id' => null,
        ];

        if (!$normalized) {
            return $empty;
        }

        if (!$customerId) {
            return array_merge($empty, [
                'message' => 'Please login to apply a coupon',
            ]);
        }

        $coupon = $this->findActiveCoupon($normalized);
        if (!$coupon) {
            return array_merge($empty, [
                'message' => 'Invalid or expired coupon code',
            ]);
        }

        $maxUses = (int) ($coupon->max_uses_per_user ?? 1);
        if ($maxUses > 0 && $this->hasRedeemed((int) $coupon->id, (int) $customerId)) {
            return array_merge($empty, [
                'message' => 'You have already used this coupon',
                'code' => $normalized,
            ]);
        }

        $discount = $this->discountAmount($coupon, $subTotal);
        if ($discount <= 0) {
            return array_merge($empty, [
                'message' => 'Coupon cannot be applied to this order',
                'code' => $normalized,
            ]);
        }

        $label = $coupon->type === 'percent'
            ? rtrim(rtrim(number_format((float) $coupon->value, 2, '.', ''), '0'), '.') . '% off coupon'
            : 'Coupon discount';

        return [
            'valid' => true,
            'code' => $normalized,
            'label' => $label,
            'discount' => $discount,
            'sub_total' => $subTotal,
            'total' => round(max(0, $subTotal - $discount), 2),
            'message' => 'Coupon applied successfully',
            'coupon_id' => (int) $coupon->id,
        ];
    }

    /**
     * Validate + lock for order placement. Call inside DB transaction.
     */
    public function applyForOrder(float $subTotal, ?string $code, ?int $customerId): array
    {
        $quote = $this->quote($subTotal, $code, $customerId);

        if (!$quote['valid'] || !$quote['coupon_id'] || !$customerId) {
            return [
                'applied' => false,
                'discount' => 0.0,
                'code' => $quote['code'],
                'label' => null,
                'coupon_id' => null,
                'message' => $quote['message'],
                'total' => round(max(0, $subTotal), 2),
            ];
        }

        Coupon::where('id', $quote['coupon_id'])->lockForUpdate()->first();

        if ($this->hasRedeemed((int) $quote['coupon_id'], (int) $customerId)) {
            return [
                'applied' => false,
                'discount' => 0.0,
                'code' => $quote['code'],
                'label' => null,
                'coupon_id' => null,
                'message' => 'You have already used this coupon',
                'total' => round(max(0, $subTotal), 2),
            ];
        }

        return [
            'applied' => true,
            'discount' => $quote['discount'],
            'code' => $quote['code'],
            'label' => $quote['label'],
            'coupon_id' => $quote['coupon_id'],
            'message' => $quote['message'],
            'total' => $quote['total'],
        ];
    }

    public function recordRedemption(int $couponId, int $customerId, int $orderId): void
    {
        CouponRedemption::create([
            'coupon_id' => $couponId,
            'customer_id' => $customerId,
            'order_id' => $orderId,
        ]);
    }
}
