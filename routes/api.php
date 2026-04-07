<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\HomepageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CustomerAddressController;
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

Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'getProfile']);
    Route::post('/user/update-profile', [UserController::class, 'updateProfile']);
    Route::post('/user/update-password', [UserController::class, 'updatePassword']);
    Route::post('/user/update-address', [UserController::class, 'updateAddress']);
    Route::post('/save-cart', [UserController::class, 'saveCart']);
    Route::get('/get-cart', [UserController::class, 'getCart']);
     Route::get('/addresses', [CustomerAddressController::class, 'index']);
    Route::post('/addresses', [CustomerAddressController::class, 'store']);
    Route::put('/addresses/{id}/default', [CustomerAddressController::class, 'setDefault']);
    Route::delete('/addresses/{id}', [CustomerAddressController::class, 'destroy']);
});
