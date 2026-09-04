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

        $customers = Customer::query()
            ->whereIn('customer_id', function ($q) {
                $q->select('customer_id')
                    ->from('push_tokens')
                    ->where('is_active', true)
                    ->whereNotNull('customer_id')
                    ->whereNotNull('p256dh');
            })
            ->orderByDesc('customer_id')
            ->limit(200)
            ->get(['customer_id', 'first_name', 'last_name', 'phone', 'email']);

        return view('backend.push.index', compact('activeCount', 'customers'));
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

        $summary = $webPush->sendToCustomer($customerId, [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'url' => $validated['url'] ?: env('STOREFRONT_URL', 'https://dhirago.com'),
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
