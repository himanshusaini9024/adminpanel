<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\HomepageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\ReturnController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/justproduct', [HomepageController::class, 'index']);
Route::get('/search', [HomepageController::class, 'search']);

Route::get('/category/{slug}', [CategoryController::class, 'show']);
Route::get('/product/{pid}', [ProductController::class, 'show']);
Route::post('/contact', [ContactController::class, 'store']);
Route::get('/products', [ProductController::class, 'index']);

Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/firebase-login', [AuthController::class, 'firebaseLogin']);
Route::any('/order-track/webhook', [WebhookController::class, 'handle']);


Route::get('/webhook/whatsapp', function (Request $request) {

    $verify_token = "EAAcM9YoBTosBRq5tAbmvxRGzsCmT3Ofx7Kicg0fU8UHpZCSCY1ygVnZCUhgJMlktByBJiT2hjEoitgV7FoU21ANZBDPZCT2WhOds793shcut9fiOquRi8Y0kCtsnZBS7yhXiZB6kCIctTFZBmm4nHp2B95SJoYWGgEk8ClQ8c5LJFxyRRN369y05oOMVzHcpAZDZD";

    $mode = $request->get('hub_mode');
    $token = $request->get('hub_verify_token');
    $challenge = $request->get('hub_challenge');

    if ($mode === 'subscribe' && $token === $verify_token) {

        Log::info('WhatsApp Webhook Verified');

        return response($challenge, 200);
    }

    return response('Verification failed', 403);
});


Route::post('/webhook/whatsapp', function (Request $request) {

    Log::info('WhatsApp Webhook Payload', $request->all());

    return response()->json([
        'status' => 'success'
    ], 200);
});

Route::middleware('auth:customer')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [UserController::class, 'getProfile']);
    Route::post('/user/update-profile', [UserController::class, 'updateProfile']);
    Route::post('/user/update-password', [UserController::class, 'updatePassword']);
    Route::post('/user/update-address', [UserController::class, 'updateAddress']);
    Route::post('/save-cart', [UserController::class, 'saveCart']);
    Route::get('/get-cart', [UserController::class, 'getCart']);
    Route::get('/addresses', [CustomerAddressController::class, 'index']);
    Route::post('/addresses', [CustomerAddressController::class, 'store']);
    Route::put('/addresses/{id}/default', [CustomerAddressController::class, 'setDefault']);
    Route::post('/razorpay/create-order', [PaymentController::class, 'createRazorpayOrder']);
    Route::post('/razorpay/verify', [PaymentController::class, 'verifyPayment']);
    Route::delete('/addresses/{id}', [CustomerAddressController::class, 'destroy']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/latest', [OrderController::class, 'latest']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/returns/create', [ReturnController::class, 'create']);

});
