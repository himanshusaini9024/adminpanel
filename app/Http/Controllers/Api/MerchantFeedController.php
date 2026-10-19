<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleMerchantFeedService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MerchantFeedController extends Controller
{
    public function xml(Request $request, GoogleMerchantFeedService $feed): Response
    {
        $this->authorizeToken($request);

        $xml = $feed->toXml($feed->items());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function tsv(Request $request, GoogleMerchantFeedService $feed): Response
    {
        $this->authorizeToken($request);

        $tsv = $feed->toTsv($feed->items());

        return response($tsv, 200, [
            'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'Content-Disposition' => 'inline; filename="google-merchant-feed.tsv"',
        ]);
    }

    public function json(Request $request, GoogleMerchantFeedService $feed)
    {
        $this->authorizeToken($request);

        $items = $feed->items();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'products' => $items,
        ])->header('Cache-Control', 'public, max-age=300');
    }

    private function authorizeToken(Request $request): void
    {
        $expected = (string) config('services.merchant.feed_token');
        if ($expected === '') {
            abort(503, 'Merchant feed token is not configured. Set MERCHANT_FEED_TOKEN in .env');
        }

        $provided = (string) ($request->query('token') ?: $request->bearerToken());
        if (!hash_equals($expected, $provided)) {
            abort(403, 'Invalid merchant feed token.');
        }
    }
}
