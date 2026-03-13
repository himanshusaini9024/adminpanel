<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\FrontendController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\PayPalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\HomeController;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;

use UniSharp\LaravelFilemanager\Lfm;

/*
|--------------------------------------------------------------------------
| Utility Routes
|--------------------------------------------------------------------------
*/

Route::get('cache-clear', function () {
    Artisan::call('optimize:clear');
    return redirect()->back();
})->name('cache.clear');

Route::get('storage-link', [AdminController::class,'storageLink'])->name('storage.link');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

Auth::routes(['register' => false]);

Route::get('user/login', [FrontendController::class,'login'])->name('login.form');
Route::post('user/login', [FrontendController::class,'loginSubmit'])->name('login.submit');
Route::get('user/logout', [FrontendController::class,'logout'])->name('user.logout');

Route::get('user/register', [FrontendController::class,'register'])->name('register.form');
Route::post('user/register', [FrontendController::class,'registerSubmit'])->name('register.submit');

/*
|--------------------------------------------------------------------------
| Password Reset
|--------------------------------------------------------------------------
*/

Route::get('password/reset',[ForgotPasswordController::class,'showLinkRequestForm'])->name('password.request');
Route::post('password/email',[ForgotPasswordController::class,'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}',[ResetPasswordController::class,'showResetForm'])->name('password.reset');
Route::post('password/reset',[ResetPasswordController::class,'reset'])->name('password.update');

/*
|--------------------------------------------------------------------------
| Social Login
|--------------------------------------------------------------------------
*/

Route::get('login/{provider}', [LoginController::class,'redirect'])->name('login.redirect');
Route::get('login/{provider}/callback', [LoginController::class,'Callback'])->name('login.callback');

/*
|--------------------------------------------------------------------------
| Home Redirect
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/home', function () {
    return redirect('/admin');
})->name('home');

/*
|--------------------------------------------------------------------------
| Frontend Routes
|--------------------------------------------------------------------------
*/

Route::get('/about-us',[FrontendController::class,'aboutUs'])->name('about-us');
Route::get('/contact',[FrontendController::class,'contact'])->name('contact');
Route::post('/contact/message',[MessageController::class,'store'])->name('contact.store');

Route::get('/product-detail/{slug}',[FrontendController::class,'productDetail'])->name('product-detail');
Route::post('/product/search',[FrontendController::class,'productSearch'])->name('product.search');

Route::get('/product-cat/{slug}',[FrontendController::class,'productCat'])->name('product-cat');
Route::get('/product-sub-cat/{slug}/{sub_slug}',[FrontendController::class,'productSubCat'])->name('product-sub-cat');
Route::get('/product-brand/{slug}',[FrontendController::class,'productBrand'])->name('product-brand');

Route::get('/product-grids',[FrontendController::class,'productGrids'])->name('product-grids');
Route::get('/product-lists',[FrontendController::class,'productLists'])->name('product-lists');
Route::match(['get','post'],'/filter',[FrontendController::class,'productFilter'])->name('shop.filter');

/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
*/

Route::get('/add-to-cart/{slug}',[CartController::class,'addToCart'])->name('add-to-cart')->middleware('user');
Route::post('/add-to-cart',[CartController::class,'singleAddToCart'])->name('single-add-to-cart')->middleware('user');

Route::get('cart-delete/{id}',[CartController::class,'cartDelete'])->name('cart-delete');
Route::post('cart-update',[CartController::class,'cartUpdate'])->name('cart.update');

Route::get('/cart',function(){
    return view('frontend.pages.cart');
})->name('cart');

Route::get('/checkout',[CartController::class,'checkout'])->name('checkout')->middleware('user');

/*
|--------------------------------------------------------------------------
| Wishlist
|--------------------------------------------------------------------------
*/

Route::get('/wishlist',function(){
    return view('frontend.pages.wishlist');
})->name('wishlist');

Route::get('/wishlist/{slug}',[WishlistController::class,'wishlist'])->name('add-to-wishlist')->middleware('user');
Route::get('wishlist-delete/{id}',[WishlistController::class,'wishlistDelete'])->name('wishlist-delete');

/*
|--------------------------------------------------------------------------
| Orders
|--------------------------------------------------------------------------
*/

Route::post('cart/order',[OrderController::class,'store'])->name('cart.order');
Route::get('order/pdf/{id}',[OrderController::class,'pdf'])->name('order.pdf');
Route::get('/income',[OrderController::class,'incomeChart'])->name('product.order.income');
Route::get('/product/track',[OrderController::class,'orderTrack'])->name('order.track');
Route::post('product/track/order',[OrderController::class,'productTrackOrder'])->name('product.track.order');

/*
|--------------------------------------------------------------------------
| Blog
|--------------------------------------------------------------------------
*/

Route::get('/blog',[FrontendController::class,'blog'])->name('blog');
Route::get('/blog-detail/{slug}',[FrontendController::class,'blogDetail'])->name('blog.detail');

Route::post('/blog/filter',[FrontendController::class,'blogFilter'])->name('blog.filter');
Route::get('blog-cat/{slug}',[FrontendController::class,'blogByCategory'])->name('blog.category');
Route::get('blog-tag/{slug}',[FrontendController::class,'blogByTag'])->name('blog.tag');

/*
|--------------------------------------------------------------------------
| Reviews & Comments
|--------------------------------------------------------------------------
*/

Route::resource('/review',ProductReviewController::class);
Route::post('product/{slug}/review',[ProductReviewController::class,'store'])->name('review.store');

Route::post('post/{slug}/comment',[PostCommentController::class,'store'])->name('post-comment.store');
Route::resource('/comment',PostCommentController::class);

/*
|--------------------------------------------------------------------------
| Coupon
|--------------------------------------------------------------------------
*/

Route::post('/coupon-store',[CouponController::class,'couponStore'])->name('coupon-store');

/*
|--------------------------------------------------------------------------
| Payment
|--------------------------------------------------------------------------
*/

Route::get('payment',[PayPalController::class,'payment'])->name('payment');
Route::get('cancel',[PayPalController::class,'cancel'])->name('payment.cancel');
Route::get('payment/success',[PayPalController::class,'success'])->name('payment.success');

/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
*/

Route::group(['prefix'=>'admin','middleware'=>['auth','admin']],function(){

    Route::get('/',[AdminController::class,'index'])->name('admin');
  Route::get('/file-manager', function () {
            return view('backend.layouts.file-manager');
        })->name('file-manager');
    Route::resource('users',App\Http\Controllers\UsersController::class);
    Route::resource('banner',App\Http\Controllers\BannerController::class);
    Route::resource('brand',App\Http\Controllers\BrandController::class);
    Route::resource('category',App\Http\Controllers\CategoryController::class);
    Route::resource('product',App\Http\Controllers\ProductController::class);
 Route::get('settings', [AdminController::class, 'settings'])->name('settings');
  Route::get('change-password', [AdminController::class, 'changePassword'])->name('change.password.form');
        Route::post('change-password', [AdminController::class, 'changPasswordStore'])->name('change.password');
    Route::resource('post-category',App\Http\Controllers\PostCategoryController::class);
    Route::resource('post-tag',App\Http\Controllers\PostTagController::class);
    Route::resource('post',App\Http\Controllers\PostController::class);

    Route::resource('review',ProductReviewController::class);
    Route::resource('comment',PostCommentController::class);

    Route::resource('message',MessageController::class);
    Route::get('/message/five',[MessageController::class,'messageFive'])->name('messages.five');

    Route::resource('order',OrderController::class);
    Route::resource('shipping',App\Http\Controllers\ShippingController::class);
    Route::resource('coupon',CouponController::class);

    Route::get('/profile',[AdminController::class,'profile'])->name('admin-profile');
    Route::post('/profile/{id}',[AdminController::class,'profileUpdate'])->name('profile-update');

    Route::get('/notifications',[NotificationController::class,'index'])->name('all.notification');
    Route::get('/notification/{id}',[NotificationController::class,'show'])->name('admin.notification');
    Route::delete('/notification/{id}',[NotificationController::class,'delete'])->name('notification.delete');

});

/*
|--------------------------------------------------------------------------
| User Panel
|--------------------------------------------------------------------------
*/

Route::group(['prefix'=>'user','middleware'=>['user']],function(){

    Route::get('/',[HomeController::class,'index'])->name('user');

    Route::get('/profile',[HomeController::class,'profile'])->name('user-profile');
    Route::post('/profile/{id}',[HomeController::class,'profileUpdate'])->name('user-profile-update');

    Route::get('/order',[HomeController::class,'orderIndex'])->name('user.order.index');
    Route::get('/order/show/{id}',[HomeController::class,'orderShow'])->name('user.order.show');

});

/*
|--------------------------------------------------------------------------
| File Manager
|--------------------------------------------------------------------------
*/

Route::group(['prefix'=>'laravel-filemanager','middleware'=>['web','auth']],function(){
    Lfm::routes();
});