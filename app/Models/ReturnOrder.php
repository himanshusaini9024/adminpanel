<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnOrder extends Model
{
    use HasFactory;

      protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'comment',
        'status',
        'reverse_awb',
          'reverse_order_id',
    'reverse_shipment_id',
    'refund_id','refund_amount','refunded_at',
        'courier'
    ];
      public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function items()
{
    return $this->hasManyThrough(
        OrderItem::class,
        Order::class,
        'id',        // Foreign key on orders table
        'order_id',  // Foreign key on order_items table
        'order_id',  // Local key on returns table
        'id'         // Local key on orders table
    );
}
}
