<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CloudinaryController extends Controller
{
    //
    public function index()
    {
        $cloudName =  "ds48lk80f";
        $apiKey = env('CLOUDIARY_API_KEY');
        $apiSecret = env('CLOUDIARY_API_SECRECTKEY');

    $url = "https://api.cloudinary.com/v1_1/$cloudName/resources/image/upload";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, "$apiKey:$apiSecret");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        return response()->json(json_decode($result));
        
    }
}