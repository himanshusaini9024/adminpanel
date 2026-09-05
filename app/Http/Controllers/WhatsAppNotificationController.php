<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppBulkJob;
use App\Models\Customer;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppNotificationController extends Controller
{
    public function index(WhatsAppService $whatsapp)
    {
        $withPhone = Customer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->count();

        $customers = Customer::query()
            ->orderByDesc('customer_id')
            ->limit(500)
            ->get(['customer_id', 'first_name', 'last_name', 'phone', 'email']);

        $configOk = $whatsapp->isConfigured();
        $template = (string) config('services.whatsapp.reminder_template', 'dhirago_customer_offer');
        $headerImage = (string) config('services.whatsapp.reminder_template_image', '');

        return view('backend.whatsapp.index', compact(
            'withPhone',
            'customers',
            'configOk',
            'template',
            'headerImage'
        ));
    }

    public function send(Request $request, WhatsAppService $whatsapp)
    {
        if ($error = $whatsapp->configError()) {
            return redirect()
                ->route('whatsapp.index')
                ->withInput()
                ->with('error', $error);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'header_image' => 'nullable|url|max:1000',
            'audience' => 'required|in:all,selected,customer',
            'customer_id' => 'required_if:audience,customer|nullable|integer',
            'customer_ids' => 'required_if:audience,selected|nullable|array',
            'customer_ids.*' => 'integer',
        ]);

        $headerImage = !empty($validated['header_image'])
            ? trim($validated['header_image'])
            : null;

        if ($headerImage === null) {
            $defaultHeader = $whatsapp->resolveHeaderImage();
            if ($defaultHeader === '') {
                return redirect()
                    ->route('whatsapp.index')
                    ->withInput()
                    ->with('error', 'Set a header image URL (JPEG/PNG) or WHATSAPP_REMINDER_TEMPLATE_IMAGE in .env.');
            }
        }

        $ids = [];

        if ($validated['audience'] === 'all') {
            $ids = Customer::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->pluck('customer_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } elseif ($validated['audience'] === 'customer') {
            $ids = [(int) $validated['customer_id']];
        } else {
            $ids = array_map('intval', $validated['customer_ids'] ?? []);
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return redirect()
                ->route('whatsapp.index')
                ->withInput()
                ->with('error', 'No customers selected (or none have a phone number).');
        }

        // Run after HTTP response so the admin UI does not time out (works with QUEUE_CONNECTION=sync).
        SendWhatsAppBulkJob::dispatchAfterResponse(
            $ids,
            $validated['message'],
            $headerImage
        );

        $count = count($ids);

        return redirect()
            ->route('whatsapp.index')
            ->with(
                'success',
                "WhatsApp bulk send started for {$count} customer(s) using template "
                . config('services.whatsapp.reminder_template', 'dhirago_customer_offer')
                . '. Check laravel.log for sent/failed counts. Recipients should see it under WhatsApp Updates.'
            );
    }
}
