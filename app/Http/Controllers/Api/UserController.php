<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function getProfile(Request $request)
    {
        // return response()->json($request->user());
            return response()->json(Auth::guard('customer')->user());

    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated'
            ], 401);
        }

        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:6|confirmed',
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json(['message' => 'Password updated']);
    }

    public function updateAddress(Request $request)
    {

        $user = $request->user();
        $request->validate([
            'city' => 'required|string',
        ]);

        $user->update([
            'address' => $request->line1 . ' ' . $request->line2 . ' ' . $request->city . ' ' . $request->state,
            'city'  => $request->city,
            'state' => $request->state,
            'zip'   => $request->zip
        ]);


        return response()->json(['message' => 'Address updated']);
    }

    public function saveCart(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        $cart = $request->input('cart');
        $items = is_array($cart) ? $cart : [];
        if (isset($items['items']) && is_array($items['items'])) {
            $items = $items['items'];
        }

        $fingerprint = $this->cartFingerprint($items);
        $previous = $this->cartFingerprint(
            method_exists($user, 'getCartItemsAttribute') ? $user->cart_items : (json_decode((string) $user->cart, true) ?: [])
        );

        $user->cart = json_encode(array_values($items));

        if ($fingerprint === '[]') {
            $user->cart_updated_at = null;
            $user->cart_reminder_sent_at = null;
        } elseif ($fingerprint !== $previous) {
            // New / changed cart → start a fresh 24h reminder window
            $user->cart_updated_at = now();
            $user->cart_reminder_sent_at = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Cart saved successfully'
        ]);
    }

    /**
     * Stable signature so login re-sync of the same items does not reset the reminder window.
     */
    private function cartFingerprint($items): string
    {
        if (!is_array($items) || $items === []) {
            return '[]';
        }

        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = [
                'id' => (string) ($item['id'] ?? $item['product_id'] ?? ''),
                'sku' => (string) ($item['sku'] ?? ''),
                'size' => (string) ($item['size'] ?? ''),
                'color' => (string) ($item['color'] ?? ''),
                'qty' => (int) ($item['quantity'] ?? $item['qty'] ?? 0),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['id'] . $a['sku'] . $a['size'], $b['id'] . $b['sku'] . $b['size']));

        return json_encode($rows);
    }

    public function getCart(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'cart' => json_decode($user->cart, true) ?? []
        ]);
    }
}
