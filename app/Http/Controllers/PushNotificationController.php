<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PushToken;
use App\Services\WebPushService;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    public function index()
    {
        $activeCount = PushToken::where('is_active', true)
            ->whereNotNull('p256dh')
            ->whereNotNull('auth')
            ->count();

        $subscribedIds = PushToken::query()
            ->where('is_active', true)
            ->whereNotNull('customer_id')
            ->whereNotNull('p256dh')
            ->whereNotNull('auth')
            ->pluck('customer_id')
            ->unique()
            ->flip();

        // Show all customers so admin can pick anyone (mark who has push)
        $customers = Customer::query()
            ->orderByDesc('customer_id')
            ->limit(500)
            ->get(['customer_id', 'first_name', 'last_name', 'phone', 'email']);

        return view('backend.push.index', compact('activeCount', 'customers', 'subscribedIds'));
    }

    public function send(Request $request, WebPushService $webPush)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'url' => 'nullable|url|max:500',
            'image' => 'nullable|url|max:1000',
            'icon' => 'nullable|url|max:1000',
            'audience' => 'required|in:all,customer',
            'customer_id' => 'required_if:audience,customer|nullable|integer',
        ]);

        $customerId = $validated['audience'] === 'customer'
            ? (int) $validated['customer_id']
            : null;

        if ($customerId) {
            $hasPush = PushToken::where('customer_id', $customerId)
                ->where('is_active', true)
                ->whereNotNull('p256dh')
                ->whereNotNull('auth')
                ->exists();

            if (!$hasPush) {
                return redirect()
                    ->route('push.index')
                    ->withInput()
                    ->with('error', 'This customer has no active browser push subscription yet. Ask them to open the website and Allow notifications while logged in.');
            }
        }

        $summary = $webPush->sendToCustomer($customerId, [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'url' => $validated['url'] ?: config('services.webpush.storefront_url', 'https://dhirago.com'),
            'image' => $validated['image'] ?? null,
            'icon' => $validated['icon'] ?? ($validated['image'] ?? null),
        ]);

        $msg = "Push sent: {$summary['sent']} delivered, {$summary['failed']} failed.";
        if (!empty($summary['errors'])) {
            $msg .= ' ' . implode(' | ', array_slice($summary['errors'], 0, 3));
        }

        return redirect()
            ->route('push.index')
            ->with($summary['sent'] > 0 ? 'success' : 'error', $msg);
    }
}
