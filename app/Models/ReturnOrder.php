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
        'order_item_id',   // NEW: which specific line item is being returned/exchanged
        'user_id',
        'reason',
        'comment',
        'status',
        'order_number',

        'type',
        'exchange_size',
        'exchange_color',
        'replacement_order_id',

        'reverse_awb',
        'reverse_order_id',
        'reverse_shipment_id',
        'refund_id',
        'refund_amount',
        'refunded_at',
        'courier',
    ];

    protected $casts = [
        'refunded_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * The single order item this return/exchange request is actually about.
     */
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * All items on the parent order (kept for reference/back-compat,
     * but prefer orderItem() when you need the specific item involved).
     */
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

    public function replacementOrder()
    {
        return $this->belongsTo(Order::class, 'replacement_order_id');
    }
}