<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show($slug)
    {
        $product = Product::getProductBySlug($slug);

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        // ✅ Decode JSON fields
        $images = json_decode($product->photo, true) ?? [];
        $sizes  = $product->size;
        $colors = $product->color;

        // ✅ Reviews
        $reviews = $product->getReview ?? collect();

        // ✅ Rating calculation
        $count = $reviews->count();
        $avg = $reviews->avg('rate') ?? 0;

        $votes = [];
        for ($i = 1; $i <= 5; $i++) {
            $votes[] = [
                'star' => $i,
                'count' => $reviews->where('rate', $i)->count()
            ];
        }

        return response()->json([
            'id' => (string) $product->id,
            'name' => $product->title,
            'slug' => $product->slug,
            'price' => (float) $product->price,
            'discount' => (int) $product->discount,
            'quantityAvailable' => (int) $product->stock,
            'category' => $product->cat_info->title ?? '',

            'currentPrice' => $this->calculatePrice($product),

            'sizes' => $sizes,
            'colors' => $colors,
            'images' => $images,

            'punctuation' => [
                'countOpinions' => $count,
                'punctuation' => round($avg, 1),
                'votes' => $votes
            ],

            'reviews' => $reviews->map(function ($review) {
                return [
                    'name' => $review->user_info->name ?? 'User',
                    'avatar' => $review->user_info->photo ?? '/images/default-user.png',
                    'description' => $review->review,
                    'punctuation' => (int) $review->rate,
                ];
            })->values()
        ]);
    }

  private function calculatePrice($product)
{
    if ($product->discount > 0) {
        return round(
            $product->price - ($product->price * $product->discount / 100),
            2
        );
    }
    return (float) $product->price;
}

    private function getRating($product)
    {
        $reviews = $product->reviews;

        $count = $reviews->count();
        $avg = $reviews->avg('rating') ?? 0;

        $votes = [];

        for ($i = 1; $i <= 5; $i++) {
            $votes[] = [
                'star' => $i,
                'count' => $reviews->where('rating', $i)->count()
            ];
        }

        return [
            'countOpinions' => $count,
            'punctuation' => round($avg, 1),
            'votes' => $votes
        ];
    }
}
