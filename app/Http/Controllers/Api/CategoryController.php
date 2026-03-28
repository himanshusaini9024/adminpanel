<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{

    public function show($slug)
    {
        $categorydata = [];

      $category = DB::select("
        SELECT categories.*, products.*
        FROM categories
        LEFT JOIN products 
            ON products.cat_id = categories.id
            AND products.slug IS NOT NULL
        WHERE categories.slug = ?
    ", [$slug]);

    if ($category) {
        foreach ($category as $key => $value) {
            $imgae = json_decode($value->photo, true);
            $categorydata[] = [
                'id' => $value->id,
                'name'=> $value->title,
                'slug'=> $value->slug,
                'cat_id'=> $value->cat_id,
                'currentPrice'=> $value->price,
                'status'=> $value->status,
                'size'=> $value->size,
                'imgae' => $imgae
                
            ];

        }
        
    }
    

    
    return response()->json([
        'category' => $categorydata
    ]);
    }
}
