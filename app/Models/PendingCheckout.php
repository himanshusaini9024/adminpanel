<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An online checkout started on the storefront. Status: pending (payment window opened),
 * paid (Razorpay confirmed, no order yet), completed (order created), failed (paid but the
 * order could not be created — needs admin attention).
 */
class PendingCheckout extends Model
{
    protected $fillable = [
        'razorpay_order_id',
        'customer_id',
        'amount',
        'payload',
        'status',
        'razorpay_payment_id',
        'paid_at',
        'order_id',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Money received but no order: what the admin needs to look at.
     */
    public function scopeNeedsAttention($query)
    {
        return $query->whereNull('order_id')->whereNotNull('paid_at');
    }
}
