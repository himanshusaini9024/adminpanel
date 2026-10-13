<?php

namespace App\Http\Controllers;

use App\Jobs\OrderPlacedJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Orders taken outside the website (WhatsApp, Instagram, phone) and paid by bank transfer.
 */
class ManualOrderController extends Controller
{
    private const PROOF_DISK = 'local';
    private const PROOF_DIR = 'payment-proofs';

    public function create()
    {
        $products = Product::where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'title', 'sku', 'price', 'discount', 'special_price', 'size', 'color']);

        $productOptions = $products->map(function (Product $product) {
            $pricing = Product::normalizePricing($product->price, $product->discount, $product->special_price);

            return [
                'id' => $product->id,
                'title' => $product->title,
                'sku' => $product->sku,
                'price' => (float) $pricing['special_price'],
                'sizes' => $this->productSizes($product),
            ];
        })->values();

        $sources = collect(Order::SOURCE_LABELS)->except('website');

        return view('backend.order.manual-create', compact('productOptions', 'sources'));
    }

    public function store(Request $request, OrderService $orderService)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address1' => 'required|string|max:500',
            'address2' => 'nullable|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|digits:6',
            'order_source' => ['required', Rule::in(array_keys(Order::SOURCE_LABELS))],
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.size' => 'nullable|string|max:20',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'items.*.price' => 'required|numeric|min:0',
            'payment_status' => 'required|in:paid,unpaid',
            'payment_reference' => 'nullable|required_if:payment_status,paid|string|max:100',
            'paid_at' => 'nullable|date',
            'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'admin_note' => 'nullable|string|max:2000',
            'notify_customer' => 'nullable|boolean',
        ], [
            'payment_reference.required_if' => 'Enter the UTR / transaction reference for a paid order.',
        ]);

        $phone = $this->normalizePhone($data['phone']);
        if (strlen($phone) !== 10) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid 10-digit mobile number.']);
        }

        $items = $this->buildItems($data['items']);
        $paid = $data['payment_status'] === 'paid';
        $proofPath = $this->storeProof($request->file('payment_proof'));

        try {
            $order = $orderService->createOrder([
                'customer_id' => $this->resolveCustomerId($phone, $data),
                'first_name' => $data['first_name'],
                'last_name' => ($data['last_name'] ?? null) ?: $data['first_name'],
                'phone' => $phone,
                'email' => $data['email'] ?? null,
                'address1' => $data['address1'],
                'address2' => $data['address2'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'],
                'pincode' => $data['pincode'],
                'items' => $items,
                'quantity' => array_sum(array_column($items, 'quantity')),
                'payment_method' => 'bank_transfer',
                'payment_status' => $data['payment_status'],
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_proof' => $proofPath,
                'paid_at' => $paid ? ($data['paid_at'] ?? now()) : null,
                'order_source' => $data['order_source'],
                'admin_note' => $data['admin_note'] ?? null,
                'skip_first_order_discount' => true,
                'hold_shipment' => !$paid,
            ]);
        } catch (\Throwable $e) {
            if ($proofPath) {
                Storage::disk(self::PROOF_DISK)->delete($proofPath);
            }
            Log::error('Manual order failed', ['message' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Could not create the order: ' . $e->getMessage());
        }

        if ($request->boolean('notify_customer')) {
            OrderPlacedJob::dispatch($order->id);
        }

        $message = 'Order #' . $order->order_number . ' created.';
        if (!$paid) {
            $message .= ' It will be sent to Shiprocket after you mark it as paid.';
        } elseif (!$order->shipment_id && env('SHIPMENT_LIVE', false)) {
            $message .= ' Shiprocket booking failed. Check the logs and create the shipment manually.';
        }

        return redirect()->route('order.show', $order->id)->with('success', $message);
    }

    public function markPaid(Request $request, $id, OrderService $orderService)
    {
        $order = Order::findOrFail($id);

        if (!$order->isAwaitingBankPayment()) {
            return back()->with('error', 'This order is not waiting for a bank transfer.');
        }

        $data = $request->validate([
            'payment_reference' => 'required|string|max:100',
            'paid_at' => 'nullable|date',
            'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'payment_reference.required' => 'Enter the UTR / transaction reference.',
        ]);

        $proofPath = $this->storeProof($request->file('payment_proof'));
        if ($proofPath && $order->payment_proof) {
            Storage::disk(self::PROOF_DISK)->delete($order->payment_proof);
        }

        $order->forceFill([
            'payment_status' => 'paid',
            'payment_reference' => $data['payment_reference'],
            'paid_at' => $data['paid_at'] ?? now(),
            'payment_proof' => $proofPath ?: $order->payment_proof,
        ])->save();

        $message = 'Payment recorded for order #' . $order->order_number . '.';
        if ($order->status !== 'cancel' && env('SHIPMENT_LIVE', false)) {
            $message .= $orderService->pushToShiprocket($order)
                ? ' Order sent to Shiprocket.'
                : ' Shiprocket booking failed. Check the logs and create the shipment manually.';
        }

        return back()->with('success', $message);
    }

    public function paymentProof($id)
    {
        $order = Order::findOrFail($id);
        $disk = Storage::disk(self::PROOF_DISK);

        abort_unless($order->payment_proof && $disk->exists($order->payment_proof), 404);

        return $disk->response($order->payment_proof);
    }

    /**
     * Turn the submitted rows into the item shape OrderService expects.
     */
    private function buildItems(array $rows): array
    {
        $products = Product::whereIn('id', array_column($rows, 'product_id'))->get()->keyBy('id');
        $items = [];
        $errors = [];

        foreach (array_values($rows) as $i => $row) {
            $product = $products[$row['product_id']];
            $sizes = $this->productSizes($product);
            $size = strtoupper(trim((string) ($row['size'] ?? '')));

            if ($sizes !== [] && !in_array($size, $sizes, true)) {
                $errors["items.$i.size"] = 'Choose a size for ' . $product->title . '.';
                continue;
            }

            $photos = json_decode((string) $product->photo, true);
            $thumb = is_array($photos) ? ($photos[0]['url'] ?? null) : null;

            $items[] = [
                'id' => $product->id,
                'name' => $product->title,
                'sku' => $product->sku,
                'thumb' => ['url' => $thumb],
                'price' => round((float) $row['price'], 2),
                'quantity' => (int) $row['quantity'],
                'size' => $size !== '' ? strtolower($size) : null,
                'color' => $product->color,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $items;
    }

    private function productSizes(Product $product): array
    {
        return array_values(array_filter(array_map(
            fn ($size) => strtoupper(trim($size)),
            explode(',', (string) $product->size)
        )));
    }

    /**
     * Customer account for this mobile number, created when missing. Website login is by
     * phone OTP and finds the account by phone, so the customer sees this order in "My orders".
     * Existing accounts only get blank fields filled in.
     */
    private function resolveCustomerId(string $phone, array $data): int
    {
        $email = $data['email'] ?? null;
        $details = array_filter([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $email && strlen($email) <= 56 ? $email : null,
            'address' => mb_substr(trim($data['address1'] . ' ' . ($data['address2'] ?? '')), 0, 250),
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['pincode'],
        ]);

        $customer = Customer::where('phone', $phone)->orderBy('customer_id')->first();

        if (!$customer) {
            return Customer::create(['phone' => $phone, 'date' => now('Asia/Kolkata')] + $details)->customer_id;
        }

        foreach ($details as $field => $value) {
            if (blank($customer->{$field})) {
                $customer->{$field} = $value;
            }
        }
        if ($customer->isDirty()) {
            $customer->save();
        }

        return $customer->customer_id;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function storeProof(?UploadedFile $file): ?string
    {
        return $file ? $file->store(self::PROOF_DIR, self::PROOF_DISK) : null;
    }
}
