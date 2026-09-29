<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ReturnOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Per-size stock. Every change goes through adjust() so it is logged in inventory_movements.
 * products.stock is kept as the sum of the product's listed sizes.
 */
class InventoryService
{
    public const LOW_STOCK_THRESHOLD = 2;

    public static function normalizeSize(?string $size): string
    {
        return strtoupper(trim((string) $size));
    }

    /**
     * Sizes offered for a product (uppercase). [''] when the product has no sizes.
     */
    public function sizesFor(Product $product): array
    {
        $sizes = array_values(array_unique(array_filter(
            array_map([self::class, 'normalizeSize'], explode(',', (string) $product->size)),
            fn ($s) => $s !== ''
        )));

        return $sizes === [] ? [''] : $sizes;
    }

    /**
     * @return array<string,int> size => stock for the product's listed sizes (missing rows = 0)
     */
    public function stockMap(Product $product): array
    {
        return $this->stockMapsFor([$product])[$product->id] ?? [];
    }

    /**
     * @param iterable<object> $products objects with id + size
     * @return array<int,array<string,int>>
     */
    public function stockMapsFor(iterable $products): array
    {
        $products = collect($products);
        if ($products->isEmpty()) {
            return [];
        }

        $rows = ProductStock::whereIn('product_id', $products->pluck('id')->all())
            ->get(['product_id', 'size', 'stock'])
            ->groupBy('product_id');

        $maps = [];
        foreach ($products as $product) {
            $sizes = $this->sizesFor($product instanceof Product ? $product : (new Product())->forceFill([
                'size' => $product->size ?? '',
            ]));
            $bySize = ($rows[$product->id] ?? collect())->pluck('stock', 'size');

            $map = [];
            foreach ($sizes as $size) {
                $map[$size] = (int) ($bySize[$size] ?? 0);
            }
            $maps[$product->id] = $map;
        }

        return $maps;
    }

    /**
     * Storefront payload: [{size, stock, inStock}], only positive stock counts as available.
     */
    public function sizeStockPayload(array $map): array
    {
        $out = [];
        foreach ($map as $size => $stock) {
            $out[] = [
                'size' => $size,
                'stock' => max(0, (int) $stock),
                'inStock' => (int) $stock > 0,
            ];
        }

        return $out;
    }

    public function totalAvailable(array $map): int
    {
        return array_sum(array_map(fn ($s) => max(0, (int) $s), $map));
    }

    /**
     * Admin sets absolute stock per size. Logs the difference for each size.
     *
     * @param array<string,int|string|null> $map size => new stock
     */
    public function setStock(Product $product, array $map, ?int $userId = null, ?string $note = null): void
    {
        DB::transaction(function () use ($product, $map, $userId, $note) {
            foreach ($this->sizesFor($product) as $size) {
                if (!array_key_exists($size, $map)) {
                    continue;
                }

                $target = max(0, (int) $map[$size]);
                $row = $this->lockRow($product->id, $size);
                $delta = $target - (int) $row->stock;

                if ($delta !== 0) {
                    $this->applyDelta($row, $delta, 'manual_adjustment', null, $note, $userId);
                }
            }

            $this->syncProductTotal($product->id);
        });
    }

    /**
     * Throws when any line cannot be fulfilled from current stock.
     *
     * @param array<int,array{product_id:int|string,size:?string,quantity:int|string,name?:?string}> $lines
     */
    public function assertAvailable(array $lines): void
    {
        $shortages = array_values(array_filter(
            $this->checkLines($lines),
            fn ($line) => !$line['missing'] && !$line['ok']
        ));

        if ($shortages !== []) {
            throw new InsufficientStockException($this->shortageMessage($shortages), $shortages);
        }
    }

    /**
     * Availability per product+size line (duplicates merged). Products that no longer exist
     * or are inactive come back with missing = true and available = 0.
     *
     * @return array<int,array{product_id:int,name:string,size:string,requested:int,available:int,ok:bool,missing:bool}>
     */
    public function checkLines(array $lines): array
    {
        $grouped = $this->groupLines($lines);
        if ($grouped === []) {
            return [];
        }

        $products = Product::whereIn('id', array_unique(array_column($grouped, 'product_id')))->get()->keyBy('id');
        $maps = $this->stockMapsFor($products);
        $result = [];

        foreach ($grouped as $line) {
            $product = $products[$line['product_id']] ?? null;
            $missing = !$product || $product->status !== 'active';
            $size = $product ? $this->resolveSizeKey($product, $line['size']) : $line['size'];
            $available = $missing ? 0 : max(0, (int) ($maps[$product->id][$size] ?? 0));

            $result[] = [
                'product_id' => $line['product_id'],
                'name' => $line['name'] ?: ($product->title ?? 'Product'),
                'size' => $size,
                'requested' => $line['quantity'],
                'available' => $available,
                'ok' => !$missing && $line['quantity'] <= $available,
                'missing' => $missing,
            ];
        }

        return $result;
    }

    /**
     * Take stock for an order's items. Runs once per order (stock_deducted_at).
     *
     * $strict = true rejects the order when stock is short (unpaid/COD).
     * Paid orders are never rejected here — the customer already paid — so stock may go
     * negative, which shows up as "oversold" in the admin inventory.
     */
    public function deductForOrder(Order $order, bool $strict = true, ?string $reason = null): void
    {
        if ($order->stock_deducted_at && !$order->stock_restored_at) {
            return;
        }

        $reason = $reason ?: ($order->order_type === 'exchange' ? 'exchange_shipped' : 'order_placed');

        DB::transaction(function () use ($order, $strict, $reason) {
            $lines = $this->groupLines($this->orderLines($order));
            $products = Product::whereIn('id', array_unique(array_column($lines, 'product_id')))->get()->keyBy('id');
            $shortages = [];
            $pending = [];

            foreach ($lines as $line) {
                $product = $products[$line['product_id']] ?? null;
                if (!$product) {
                    continue;
                }

                $size = $this->resolveSizeKey($product, $line['size']);
                $row = $this->lockRow($product->id, $size);

                if ($strict && $line['quantity'] > max(0, (int) $row->stock)) {
                    $shortages[] = $this->shortage($product, $line['name'], $size, max(0, (int) $row->stock));
                    continue;
                }

                $pending[] = [$row, $line['quantity']];
            }

            if ($shortages !== []) {
                throw new InsufficientStockException($this->shortageMessage($shortages), $shortages);
            }

            foreach ($pending as [$row, $quantity]) {
                $movement = $this->applyDelta($row, -$quantity, $reason, $order, null, null);
                if ($movement->stock_after < 0) {
                    Log::warning('Inventory oversold', [
                        'order_id' => $order->id,
                        'product_id' => $row->product_id,
                        'size' => $row->size,
                        'stock_after' => $movement->stock_after,
                    ]);
                }
            }

            foreach ($products->keys() as $productId) {
                $this->syncProductTotal((int) $productId);
            }

            $order->forceFill([
                'stock_deducted_at' => now(),
                'stock_restored_at' => null,
            ])->saveQuietly();
        });
    }

    /**
     * Give back an order's stock (e.g. cancelled). Only if it was deducted and not yet restored.
     */
    public function restoreForOrder(Order $order, string $reason = 'order_cancelled'): void
    {
        if (!$order->stock_deducted_at || $order->stock_restored_at) {
            return;
        }

        DB::transaction(function () use ($order, $reason) {
            $lines = $this->groupLines($this->orderLines($order));
            $products = Product::whereIn('id', array_unique(array_column($lines, 'product_id')))->get()->keyBy('id');

            foreach ($lines as $line) {
                $product = $products[$line['product_id']] ?? null;
                if (!$product) {
                    continue;
                }

                $row = $this->lockRow($product->id, $this->resolveSizeKey($product, $line['size']));
                $this->applyDelta($row, $line['quantity'], $reason, $order, null, null);
            }

            foreach ($products->keys() as $productId) {
                $this->syncProductTotal((int) $productId);
            }

            $order->forceFill(['stock_restored_at' => now()])->saveQuietly();
        });
    }

    /**
     * Returned / exchanged item reached the warehouse — put it back in stock once.
     */
    public function restockReturn(ReturnOrder $return): void
    {
        if ($return->restocked_at) {
            return;
        }

        $item = $return->orderItem()->first();
        $product = $item ? Product::find($item->product_id) : null;

        if (!$item || !$product) {
            Log::warning('Inventory restock skipped: order item/product missing', ['return_id' => $return->id]);
            return;
        }

        DB::transaction(function () use ($return, $item, $product) {
            $row = $this->lockRow($product->id, $this->resolveSizeKey($product, $item->size));
            $reason = $return->type === 'exchange' ? 'exchange_received' : 'return_received';

            $this->applyDelta($row, max(1, (int) $item->quantity), $reason, $return, null, null);
            $this->syncProductTotal($product->id);

            $return->forceFill(['restocked_at' => now()])->saveQuietly();
        });
    }

    public function syncProductTotal(int $productId): void
    {
        $product = Product::find($productId);
        if (!$product) {
            return;
        }

        $total = $this->totalAvailable($this->stockMap($product));
        if ((int) $product->stock !== $total) {
            $product->forceFill(['stock' => $total])->saveQuietly();
        }
    }

    /**
     * Map an order-item size to the stock row key. Products without sizes use ''.
     */
    public function resolveSizeKey(Product $product, ?string $size): string
    {
        $sizes = $this->sizesFor($product);

        return $sizes === [''] ? '' : self::normalizeSize($size);
    }

    private function lockRow(int $productId, string $size): ProductStock
    {
        $row = ProductStock::where('product_id', $productId)
            ->where('size', $size)
            ->lockForUpdate()
            ->first();

        return $row ?: ProductStock::create([
            'product_id' => $productId,
            'size' => $size,
            'stock' => 0,
        ]);
    }

    private function applyDelta(
        ProductStock $row,
        int $delta,
        string $reason,
        $reference,
        ?string $note,
        ?int $userId
    ): InventoryMovement {
        $row->stock = (int) $row->stock + $delta;
        $row->save();

        return InventoryMovement::create([
            'product_id' => $row->product_id,
            'size' => $row->size,
            'change' => $delta,
            'stock_after' => $row->stock,
            'reason' => $reason,
            'reference_type' => $reference ? class_basename($reference) : null,
            'reference_id' => $reference?->getKey(),
            'note' => $note,
            'user_id' => $userId,
        ]);
    }

    private function orderLines(Order $order): array
    {
        return $order->items()->get()->map(fn ($item) => [
            'product_id' => $item->product_id,
            'size' => $item->size,
            'quantity' => $item->quantity,
            'name' => $item->name,
        ])->all();
    }

    /**
     * Merge duplicate product+size lines and drop invalid ones.
     */
    private function groupLines(array $lines): array
    {
        $grouped = [];
        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);
            if ($productId < 1 || $quantity < 1) {
                continue;
            }

            $size = self::normalizeSize($line['size'] ?? '');
            $key = $productId . '|' . $size;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'product_id' => $productId,
                    'size' => $size,
                    'quantity' => 0,
                    'name' => $line['name'] ?? null,
                ];
            }
            $grouped[$key]['quantity'] += $quantity;
        }

        return array_values($grouped);
    }

    private function shortage(Product $product, ?string $name, string $size, int $available): array
    {
        return [
            'product_id' => $product->id,
            'name' => $name ?: $product->title,
            'size' => $size,
            'available' => $available,
        ];
    }

    private function shortageMessage(array $shortages): string
    {
        $parts = array_map(function ($s) {
            $label = $s['name'] . ($s['size'] !== '' ? ' (size ' . $s['size'] . ')' : '');

            return $s['available'] > 0
                ? "Only {$s['available']} left of {$label}"
                : "{$label} is out of stock";
        }, $shortages);

        return implode('. ', $parts) . '. Please update your cart.';
    }
}
