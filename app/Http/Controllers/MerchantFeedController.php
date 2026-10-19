<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\GoogleMerchantFeedService;
use Illuminate\Support\Str;

class MerchantFeedController extends Controller
{
    public function index(GoogleMerchantFeedService $feed)
    {
        $token = (string) config('services.merchant.feed_token');
        $configured = $token !== '';

        $xmlUrl = $configured ? url('/api/merchant/feed.xml?token=' . urlencode($token)) : null;
        $tsvUrl = $configured ? url('/api/merchant/feed.tsv?token=' . urlencode($token)) : null;
        $jsonUrl = $configured ? url('/api/merchant/products.json?token=' . urlencode($token)) : null;

        $activeCount = Product::where('status', 'active')->whereNotNull('slug')->count();
        $feedCount = $configured ? $feed->items()->count() : 0;
        $skipped = max(0, $activeCount - $feedCount);

        return view('backend.merchant-feed.index', [
            'configured' => $configured,
            'xmlUrl' => $xmlUrl,
            'tsvUrl' => $tsvUrl,
            'jsonUrl' => $jsonUrl,
            'activeCount' => $activeCount,
            'feedCount' => $feedCount,
            'skipped' => $skipped,
            'suggestedToken' => Str::random(40),
            'brand' => config('services.merchant.brand'),
            'storefront' => config('services.merchant.storefront_url'),
            'country' => config('services.merchant.country'),
            'currency' => config('services.merchant.currency'),
        ]);
    }
}
