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
            $results = DB::table('products')
                ->select(
                    'id',
                    'title as name',
                    'slug',
                    'photo',
                    DB::raw("'product' as type"),
                    DB::raw("
            CASE
                WHEN title LIKE '{$query}%' THEN 1
                ELSE 2
            END as relevance
        ")
                )
                ->where('title', 'LIKE', "%{$query}%")
                ->unionAll(
                    DB::table('categories')
                        ->select(
                            'id',
                            'title as name',
                            'slug',
                            'photo',
                            DB::raw("'category' as type"),
                            DB::raw("
                    CASE
                        WHEN title LIKE '{$query}%' THEN 1
                        ELSE 2
                    END as relevance
                ")
                        )
                        ->where('title', 'LIKE', "%{$query}%")->where('status', 'active')
                );

            $results = DB::query()
                ->fromSub($results, 'search')
                ->orderBy('relevance')
                ->limit(10)
                ->get();

            // 🔥 SUGGESTIONS (FAST AUTOCOMPLETE)
            $suggestions = DB::table('products')
                ->where('title', 'LIKE', $query . '%')
                ->limit(5)
                ->pluck('title');

            return response()->json([
                'results' => $results,
                'suggestions' => $suggestions
            ]);
        });
    }
}
