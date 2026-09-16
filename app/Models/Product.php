<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Cart;

class Product extends Model
{
    /** Default % off MRP when neither special_price nor discount is set. */
    public const DEFAULT_DISCOUNT_PERCENT = 15;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description',
        'color',
        'cat_id',
        'child_cat_id',
        'price',
        'special_price',
        'brand_id',
        'discount',
        'status',
        'photo',
        'size',
        'stock',
        'is_featured',
        'sort_order',
        'condition',
        'measurements',
        'sku',
    ];

    /**
     * Normalize MRP / discount / special_price.
     * Priority: discount % → special_price → default 15% off MRP.
     * (Discount wins so admin % changes always recalculate selling price.)
     *
     * @return array{price: float, discount: float, special_price: float}
     */
    public static function normalizePricing($price, $discount = null, $specialPrice = null): array
    {
        $mrp = round(max(0, (float) $price), 2);
        $discountVal = ($discount === null || $discount === '') ? null : (float) $discount;
        $specialVal = ($specialPrice === null || $specialPrice === '') ? null : (float) $specialPrice;

        if ($mrp <= 0) {
            return [
                'price' => 0.0,
                'discount' => (float) self::DEFAULT_DISCOUNT_PERCENT,
                'special_price' => 0.0,
            ];
        }

        if ($discountVal !== null && $discountVal > 0) {
            $discountVal = min(100, max(0, $discountVal));
            $specialVal = round($mrp * (1 - ($discountVal / 100)), 2);
        } elseif ($specialVal !== null && $specialVal > 0) {
            $specialVal = round(min($specialVal, $mrp), 2);
            $discountVal = $mrp > 0
                ? round((($mrp - $specialVal) / $mrp) * 100, 2)
                : 0.0;
        } else {
            $discountVal = (float) self::DEFAULT_DISCOUNT_PERCENT;
            $specialVal = round($mrp * (1 - ($discountVal / 100)), 2);
        }

        return [
            'price' => $mrp,
            'discount' => $discountVal,
            'special_price' => $specialVal,
        ];
    }

    public function sellingPrice(): float
    {
        $normalized = self::normalizePricing(
            $this->price,
            $this->discount,
            $this->special_price
        );

        return (float) $normalized['special_price'];
    }

    public function mrp(): float
    {
        return round((float) ($this->price ?? 0), 2);
    }

    public function discountPercent(): float
    {
        $normalized = self::normalizePricing(
            $this->price,
            $this->discount,
            $this->special_price
        );

        return (float) $normalized['discount'];
    }

    public function cat_info()
    {
        return $this->hasOne('App\Models\Category', 'id', 'cat_id');
    }

    public function sub_cat_info()
    {
        return $this->hasOne('App\Models\Category', 'id', 'child_cat_id');
    }

    public static function getAllProduct()
    {
        return Product::with(['cat_info', 'sub_cat_info'])
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc')
            ->paginate(10);
    }

    public function rel_prods()
    {
        return $this->hasMany('App\Models\Product', 'cat_id', 'cat_id')
            ->where('status', 'active')
            ->orderBy('id', 'DESC')
            ->limit(8);
    }

    public function getReview()
    {
        return $this->hasMany('App\Models\ProductReview', 'product_id', 'id')
            ->with('user_info')
            ->where('status', 'active')
            ->orderBy('id', 'DESC');
    }

    public static function getProductBySlug($slug)
    {
        return Product::with(['cat_info', 'rel_prods', 'getReview'])
            ->where('slug', $slug)
            ->first();
    }

    public static function countActiveProduct()
    {
        $data = Product::where('status', 'active')->count();

        return $data ?: 0;
    }

    public function carts()
    {
        return $this->hasMany(Cart::class)->whereNotNull('order_id');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class)->whereNotNull('cart_id');
    }

    public function brand()
    {
        return $this->hasOne(Brand::class, 'id', 'brand_id');
    }
}
