<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function show(Request $request, $slug)
    {
        $size = $request->query('size');
        $color = $request->query('color');
        $sort = $request->query('sort');
        $status = 'active';

        $category = Category::where('slug', $slug)
            ->where('status', $status)
            ->first();

        if (!$category) {
            return response()->json([
                'category' => [],
                'catbanner' => null,
                'catbanner_mobile' => null,
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        // Products linked via pivot (multi-category) OR legacy cat_id / child_cat_id
        $products = DB::table('products')
            ->where('products.status', $status)
            ->whereNotNull('products.slug')
            ->where(function ($q) use ($category) {
                $q->whereExists(function ($sub) use ($category) {
                    $sub->select(DB::raw(1))
                        ->from('category_product')
                        ->whereColumn('category_product.product_id', 'products.id')
                        ->where('category_product.category_id', $category->id);
                })
                ->orWhere('products.cat_id', $category->id)
                ->orWhere('products.child_cat_id', $category->id);
            })
            ->select(
                'products.id',
                'products.title as name',
                'products.slug',
                'products.cat_id',
                'products.sku',
                'products.price',
                'products.special_price',
                'products.discount',
                'products.status',
                'products.size',
                'products.color',
                'products.photo',
                'products.sort_order'
            )
            ->distinct();

        if ($size) {
            $products->where('products.size', 'LIKE', "%$size%");
        }

        if ($color) {
            $products->where('products.color', $color);
        }

        if ($sort === 'low') {
            $products->orderBy('products.price', 'asc');
        } elseif ($sort === 'high') {
            $products->orderBy('products.price', 'desc');
        } else {
            $products->orderByRaw('products.sort_order IS NULL')
                ->orderBy('products.sort_order', 'asc')
                ->orderBy('products.id', 'desc');
        }

        $result = $products->get();
        $formatted = [];

        foreach ($result as $item) {
            $images = json_decode($item->photo ?? '[]', true);

            if (!is_array($images)) {
                $images = [];
            }

            usort($images, function ($a, $b) {
                $ao = is_array($a) ? ($a['sort_order'] ?? null) : null;
                $bo = is_array($b) ? ($b['sort_order'] ?? null) : null;
                if ($ao === null && $bo === null) return 0;
                if ($ao === null) return 1;
                if ($bo === null) return -1;
                return (int) $ao <=> (int) $bo;
            });

            $pricing = \App\Models\Product::normalizePricing(
                $item->price,
                $item->discount,
                $item->special_price
            );

            $formatted[] = [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'slug' => $item->slug,
                'cat_id' => $item->cat_id,
                'category' => $category->title,
                'price' => (float) $pricing['price'],
                'mrp' => (float) $pricing['price'],
                'special_price' => (float) $pricing['special_price'],
                'discount' => (float) $pricing['discount'],
                'currentPrice' => (float) $pricing['special_price'],
                'status' => $item->status,
                'size' => $item->size,
                'color' => $item->color,
                'sort_order' => $item->sort_order,
                'image' => array_values($images),
            ];
        }

        return response()->json([
            'category' => $formatted,
            'catbanner' => $category->photo,
            'catbanner_mobile' => $category->photo_mobile,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
