<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CouponDiscountService;
use App\Services\FirstOrderDiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    /**
     * Preview checkout totals including optional first-order + coupon discounts.
     */
    public function quote(
        Request $request,
        FirstOrderDiscountService $firstOrderDiscount,
        CouponDiscountService $couponDiscount
    ) {
        $customerId = Auth::guard('customer')->id();

        if (!$customerId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'sub_total' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|integer|min:0',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $subTotal = isset($data['items']) && count($data['items'])
            ? $firstOrderDiscount->subtotalFromItems($data['items'])
            : round((float) ($data['sub_total'] ?? 0), 2);

        $firstQuote = $firstOrderDiscount->quote($subTotal, $customerId);
        $afterFirst = $firstQuote['total'];

        $couponQuote = $couponDiscount->quote(
            $afterFirst,
            $data['coupon_code'] ?? null,
            $customerId
        );

        $couponDiscountAmount = $couponQuote['valid'] ? $couponQuote['discount'] : 0.0;
        $total = round(max(0, $afterFirst - $couponDiscountAmount), 2);
        $combinedDiscount = round($firstQuote['discount'] + $couponDiscountAmount, 2);

        return response()->json([
            'success' => true,
            'eligible' => $firstQuote['eligible'],
            'percent' => $firstQuote['percent'],
            'label' => $firstQuote['label'],
            'sub_total' => $subTotal,
            'discount' => $firstQuote['discount'],
            'coupon' => [
                'valid' => $couponQuote['valid'],
                'code' => $couponQuote['code'],
                'label' => $couponQuote['label'],
                'discount' => $couponDiscountAmount,
                'message' => $couponQuote['message'],
            ],
            'coupon_discount' => $couponDiscountAmount,
            'total_discount' => $combinedDiscount,
            'total' => $total,
            'enabled' => $firstQuote['enabled'],
            'message' => $couponQuote['valid']
                ? ($couponQuote['message'] ?: 'Coupon applied')
                : ($firstQuote['eligible'] ? '10% off applied on your first order' : $couponQuote['message']),
        ]);
    }
}
