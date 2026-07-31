<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'price',
        'order_number',
        'sku',
        'quantity',
        'size',
        'color',
        'image',
    ];
    public function product()
{
    return $this->belongsTo(Product::class);
}
}
