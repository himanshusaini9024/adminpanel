<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory)
    {
    }

    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $search = trim((string) $request->query('q', ''));

        $query = Product::query()
            ->select('id', 'title', 'sku', 'slug', 'size', 'stock', 'status', 'photo')
            ->orderBy('title');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->get();
        $maps = $this->inventory->stockMapsFor($products);
        $low = InventoryService::LOW_STOCK_THRESHOLD;

        $rows = $products->map(function (Product $product) use ($maps, $low) {
            $map = $maps[$product->id] ?? [];
            $values = array_values($map);

            return [
                'product' => $product,
                'map' => $map,
                'total' => $this->inventory->totalAvailable($map),
                'out' => $values !== [] && max($values) <= 0,
                'some_out' => in_array(true, array_map(fn ($s) => $s <= 0, $values), true),
                'low' => in_array(true, array_map(fn ($s) => $s > 0 && $s <= $low, $values), true),
                'oversold' => $values !== [] && min($values) < 0,
            ];
        });

        $counts = [
            'all' => $rows->count(),
            'out' => $rows->where('out', true)->count(),
            'some_out' => $rows->where('some_out', true)->count(),
            'low' => $rows->where('low', true)->count(),
        ];

        $rows = match ($filter) {
            'out' => $rows->where('out', true),
            'some_out' => $rows->where('some_out', true),
            'low' => $rows->where('low', true),
            default => $rows,
        };

        return view('backend.inventory.index', [
            'rows' => $rows->values(),
            'counts' => $counts,
            'filter' => $filter,
            'search' => $search,
            'lowThreshold' => $low,
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'size_stock' => 'required|array',
            'size_stock.*' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        $map = [];
        foreach ($data['size_stock'] as $key => $value) {
            $map[$key === '_none' ? '' : InventoryService::normalizeSize($key)] = (int) $value;
        }

        $this->inventory->setStock($product, $map, Auth::id(), $data['note'] ?? 'Inventory page');
        $this->revalidateStorefront($product->slug);

        return back()->with('success', 'Stock updated for ' . $product->title);
    }

    public function history(Request $request)
    {
        $productId = $request->query('product');

        $movements = InventoryMovement::with(['product:id,title,sku', 'user:id,name'])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $product = $productId ? Product::select('id', 'title', 'sku')->find($productId) : null;

        $orderIds = $movements->getCollection()
            ->where('reference_type', 'Order')
            ->pluck('reference_id')
            ->unique()
            ->all();
        $orderNumbers = Order::whereIn('id', $orderIds)->pluck('order_number', 'id');

        return view('backend.inventory.history', compact('movements', 'product', 'orderNumbers'));
    }

    private function revalidateStorefront(?string $slug): void
    {
        $storefront = rtrim((string) env('STOREFRONT_URL', ''), '/');
        $secret = (string) env('REVALIDATE_SECRET', '');

        if ($storefront === '' || $secret === '') {
            return;
        }

        try {
            Http::timeout(5)->asJson()->post($storefront . '/api/revalidate', [
                'secret' => $secret,
                'slug' => $slug,
                'tags' => array_values(array_filter([
                    'products',
                    'categories',
                    $slug ? ('product-' . $slug) : null,
                ])),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Storefront revalidate failed', ['slug' => $slug, 'message' => $e->getMessage()]);
        }
    }
}
