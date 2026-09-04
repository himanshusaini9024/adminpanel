<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->orderByDesc('customer_id');

        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->get('has_cart') === '1') {
            $query->whereNotNull('cart')
                ->where('cart', '!=', '')
                ->where('cart', '!=', '[]')
                ->where('cart', '!=', 'null');
        }

        $customers = $query->paginate(15)->withQueryString();

        return view('backend.customer.index', compact('customers'));
    }

    public function edit($id)
    {
        $customer = Customer::with('addresses')->findOrFail($id);
        $cartItems = $this->resolveCartItems($customer);
        $orders = Order::where('customer_id', $customer->customer_id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('backend.customer.edit', compact('customer', 'cartItems', 'orders'));
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'nullable|string|max:100',
            'email'      => 'nullable|email|max:255',
            'phone'      => 'nullable|string|max:20',
            'address'    => 'nullable|string|max:1000',
            'city'       => 'nullable|string|max:100',
            'state'      => 'nullable|string|max:100',
            'zip'        => 'nullable|string|max:20',
            'password'   => 'nullable|string|min:6|confirmed',
        ]);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $customer->update($validated);

        return redirect()
            ->route('customer.edit', $customer->customer_id)
            ->with('success', 'Customer details updated successfully');
    }

    /**
     * Send reminder / offer message via email and/or WhatsApp.
     */
    public function sendMessage(Request $request, $id, WhatsAppService $whatsapp)
    {
        $customer = Customer::findOrFail($id);
  
        $validated = $request->validate([
            'channel' => 'required|in:email,whatsapp,both',
            'subject' => 'required_if:channel,email,both|nullable|string|max:200',
            'message' => 'required|string|max:4000',
        ]);

        $channel = $validated['channel'];
        $subject = $validated['subject'] ?: 'Message from ' . config('app.name', 'Dhirago');
        $body    = $validated['message'];
        $results = [];

        // Email
        if (in_array($channel, ['email', 'both'], true)) {
            if (empty($customer->email)) {
                $results[] = 'Email skipped: customer has no email.';
            } else {
                try {
                    $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Customer';
                    Mail::send('emails.customer-message', [
                        'customer' => $customer,
                        'name'     => $name,
                        'body'     => $body,
                        'subject'  => $subject,
                    ], function ($message) use ($customer, $subject) {
                        $message->to($customer->email)->subject($subject);
                    });
                    $results[] = 'Email sent to ' . $customer->email;
                } catch (\Throwable $e) {
                    Log::error('Customer email failed', ['error' => $e->getMessage(), 'customer_id' => $id]);
                    $results[] = 'Email failed: ' . $e->getMessage();
                }
            }
        }

        // WhatsApp
        if (in_array($channel, ['whatsapp', 'both'], true)) {
            if (empty($customer->phone)) {
                $results[] = 'WhatsApp skipped: customer has no phone.';
            } else {
                try {
                    $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Customer';
                    $result = $whatsapp->sendCustomerOutreach($customer->phone, $name, $body);
                    $results[] = $result['message'];
                } catch (\Throwable $e) {
                    Log::error('Customer WhatsApp failed', ['error' => $e->getMessage(), 'customer_id' => $id]);
                    $results[] = 'WhatsApp failed: ' . $e->getMessage();
                }
            }
        }

        return redirect()
            ->route('customer.edit', $customer->customer_id)
            ->with('success', implode(' | ', $results));
    }

    /**
     * Send a custom browser push notification to one customer.
     */
    public function sendPush(Request $request, $id, \App\Services\WebPushService $webPush)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
            'url' => 'nullable|url|max:500',
            'image' => 'nullable|url|max:1000',
            'icon' => 'nullable|url|max:1000',
        ]);

        $summary = $webPush->sendToCustomer((int) $customer->customer_id, [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'url' => $validated['url'] ?: env('STOREFRONT_URL', 'https://dhirago.com'),
            'image' => $validated['image'] ?? null,
            'icon' => $validated['icon'] ?? ($validated['image'] ?? null),
        ]);

        $msg = "Push sent: {$summary['sent']} delivered, {$summary['failed']} failed.";

        return redirect()
            ->route('customer.edit', $customer->customer_id)
            ->with($summary['sent'] > 0 ? 'success' : 'error', $msg);
    }

    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return redirect()
            ->route('customer.index')
            ->with('success', 'Customer deleted successfully');
    }

    /**
     * Decode customers.cart JSON and enrich with product records when possible.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveCartItems(Customer $customer): array
    {
        $raw = $customer->cart;
        if (empty($raw) || $raw === 'null' || $raw === '[]') {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }

        // Some clients wrap items under "items" / "cart"
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            $decoded = $decoded['items'];
        } elseif (isset($decoded['cart']) && is_array($decoded['cart'])) {
            $decoded = $decoded['cart'];
        }

        $items = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            $productId = $row['id'] ?? $row['product_id'] ?? $row['productId'] ?? null;
            $product = $productId ? Product::find($productId) : null;

            $thumb = $row['thumb']['url']
                ?? $row['image']
                ?? $row['photo']
                ?? ($product ? $product->photo : null);

            // Product photo may be JSON array of paths
            if (is_string($thumb) && str_starts_with(trim($thumb), '[')) {
                $photos = json_decode($thumb, true);
                $thumb = is_array($photos) ? ($photos[0]['url'] ?? $photos[0] ?? null) : $thumb;
            }

            $items[] = [
                'product_id' => $productId,
                'name'       => $row['name'] ?? $row['title'] ?? ($product->title ?? 'Product'),
                'sku'        => $row['sku'] ?? ($product->sku ?? ''),
                'price'      => (float) ($row['price'] ?? $product->price ?? 0),
                'quantity'   => (int) ($row['quantity'] ?? $row['qty'] ?? 1),
                'size'       => $row['size'] ?? null,
                'color'      => $row['color'] ?? null,
                'image'      => $thumb,
                'product'    => $product,
            ];
        }

        return $items;
    }
}
