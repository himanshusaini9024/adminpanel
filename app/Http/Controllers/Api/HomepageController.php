<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
}
