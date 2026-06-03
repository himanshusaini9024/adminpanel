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

        $user->cart = json_encode($cart);
        $user->save();

        return response()->json([
            'message' => 'Cart saved successfully'
        ]);
    }

    public function getCart(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'cart' => json_decode($user->cart, true) ?? []
        ]);
    }
}
