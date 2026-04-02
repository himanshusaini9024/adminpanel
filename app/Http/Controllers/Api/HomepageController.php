<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HomepageController extends Controller
{
    public function index(Request $request)
    {
        $products = config('product');

        return response()->json([
            "success" => true,
            "data" => $products
        ]);
    }


    public function search(Request $request)
    {
        $query = trim($request->q);

        if (!$query) {
            return response()->json([
                'products' => [],
                'categories' => [],
                'suggestions' => []
            ]);
        }

        $cacheKey = "search_" . md5($query);

        return Cache::remember($cacheKey, 30, function () use ($query) {

            // 🔥 PRODUCTS
            $products = DB::table('products')
                ->select(
                    'id',
                    DB::raw('title as name'),
                    'slug',
                    'photo',
                    DB::raw("'product' as type"),
                    DB::raw("
                    (CASE 
                        WHEN title LIKE ? THEN 0
                        WHEN title LIKE ? THEN 1
                        WHEN title LIKE ? THEN 2
                        ELSE 3
                    END) as relevance
                ")
                )
                ->addBinding([
                    $query,
                    $query . '%',
                    '%' . $query . '%'
                ], 'select')
                ->where('title', 'LIKE', '%' . $query . '%')
                ->orderBy('relevance') // ✅ SORT FIX
                ->limit(5)
                ->get();


            // 🔥 CATEGORIES
            $categories = DB::table('categories')
                ->select(
                    'id',
                    DB::raw('title as name'), // ✅ unified field
                    'slug',
                    'photo',
                    DB::raw("'category' as type"),
                    DB::raw("
                    (CASE 
                        WHEN title LIKE ? THEN 0
                        WHEN title LIKE ? THEN 1
                        WHEN title LIKE ? THEN 2
                        ELSE 3
                    END) as relevance
                ")
                )
                ->addBinding([
                    $query,
                    $query . '%',
                    '%' . $query . '%'
                ], 'select')
                ->where('title', 'LIKE', '%' . $query . '%')
                ->orderBy('relevance') // ✅ SORT FIX
                ->limit(5)
                ->get();


            // 🔥 SUGGESTIONS (FAST AUTOCOMPLETE)
            $suggestions = DB::table('products')
                ->whereRaw("LOWER(title) LIKE ?", [strtolower($query) . '%'])
                ->limit(5)
                ->pluck('title');

            return response()->json([
                'products' => $products,
                'categories' => $categories,
                'suggestions' => $suggestions
            ]);
        });
    }
}
