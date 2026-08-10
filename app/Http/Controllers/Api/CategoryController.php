<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{


    public function show(Request $request, $slug)
    {
        // ✅ Get query params
        $size = $request->query('size');
        $color = $request->query('color');
        $sort = $request->query('sort');
        $status = 'active';

        // ✅ Base Query
        $products = DB::table('products')
            ->join('categories', 'products.cat_id', '=', 'categories.id')
            ->where('categories.slug', $slug)
            ->whereNotNull('products.slug')
            ->where('products.status' , $status)
            ->where('categories.status' , $status)
            ->select(
                'products.id',
                'products.title as name',
                'products.slug',
                'categories.photo as banner',
                'categories.photo_mobile as banner_mobile',
                'products.cat_id',
                'products.sku',
                'products.price as currentPrice',
                'products.status',
                'products.size',
                'products.color',
                'products.photo',
                'products.sort_order'
            );

        // ✅ FILTER: Size
        if ($size) {
            $products->where('products.size', 'LIKE', "%$size%");
        }

        // ✅ FILTER: Color
        if ($color) {
            $products->where('products.color', $color);
        }

        // ✅ SORTING
        if ($sort === 'low') {
            $products->orderBy('products.price', 'asc');
        } elseif ($sort === 'high') {
            $products->orderBy('products.price', 'desc');
        } else {
            // Manual catalog order from admin (nulls last), then newest
            $products->orderByRaw('products.sort_order IS NULL')
                ->orderBy('products.sort_order', 'asc')
                ->orderBy('products.id', 'desc');
        }

        // ✅ GET DATA
        $result = $products->get();

        // ✅ FORMAT RESPONSE
        $formatted = [];
        $catbanner = null;
        $catbannerMobile = null;

        foreach ($result as $item) {
            $images = json_decode($item->photo ?? '[]', true);
                   if (!$catbanner) {
        $catbanner = $item->banner;
        $catbannerMobile = $item->banner_mobile ?? null;
    }

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

            $formatted[] = [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'slug' => $item->slug,
                'cat_id' => $item->cat_id,
                'currentPrice' => $item->currentPrice,
                'status' => $item->status,
                'size' => $item->size,
                'color' => $item->color,
                'sort_order' => $item->sort_order,
                'image' => array_values($images),
            ];
        }

        return response()->json([
            'category' => $formatted,
            'catbanner' => $catbanner,
            'catbanner_mobile' => $catbannerMobile,
        ]);
    }
}
