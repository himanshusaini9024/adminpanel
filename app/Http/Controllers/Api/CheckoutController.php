<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirstOrderDiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    /**
     * Preview checkout totals including automatic first-order 10% off.
     */
    public function quote(Request $request, FirstOrderDiscountService $discount)
    {
        $customerId = Auth::guard('customer')->id();

        if (!$customerId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'sub_total' => 'nullable|numeric|min:0',
            'items'     => 'nullable|array',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'nullable|integer|min:0',
        ]);

        $subTotal = isset($data['items']) && count($data['items'])
            ? $discount->subtotalFromItems($data['items'])
            : round((float) ($data['sub_total'] ?? 0), 2);

        $quote = $discount->quote($subTotal, $customerId);

        return response()->json([
            'success' => true,
            'eligible' => $quote['eligible'],
            'percent' => $quote['percent'],
            'label' => $quote['label'],
            'sub_total' => $quote['sub_total'],
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'message' => $quote['eligible']
                ? '10% off applied on your first order'
                : null,
        ]);
    }
}
