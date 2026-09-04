<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'browser' => ['nullable', 'string', 'max:255'],
        ]);

        $customerId = auth('customer')->id();

        PushToken::updateOrCreate(
            ['token' => $validated['endpoint']],
            [
                'customer_id' => $customerId,
                'device_type' => 'web',
                'browser' => $validated['browser'] ?? $request->userAgent(),
                'p256dh' => $validated['keys']['p256dh'],
                'auth' => $validated['keys']['auth'],
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved.',
        ]);
    }
}
