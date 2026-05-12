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
            ->select(
                'products.id',
                'products.title as name',
                'products.slug',
                'categories.photo as banner',
                'products.cat_id',
                'products.price as currentPrice',
                'products.status',
                'products.size',
                'products.color',
                'products.photo'
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
            $products->orderBy('products.id', 'desc'); // default
        }

        // ✅ GET DATA
        $result = $products->get();

        // ✅ FORMAT RESPONSE
        $formatted = [];
        $catbanner = null;

        foreach ($result as $item) {
            $images = json_decode($item->photo ?? '[]', true);
                   if (!$catbanner) {
        $catbanner = $item->banner;
    }

            if (!is_array($images)) {
                $images = [];
            }
            $formatted[] = [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'cat_id' => $item->cat_id,
                'currentPrice' => $item->currentPrice,
                'status' => $item->status,
                'size' => $item->size,
                'color' => $item->color,
                'image' => $images ?? [],
            ];
        }

        return response()->json([
            'category' => $formatted,
            'catbanner' => $catbanner
        ]);
    }
}
