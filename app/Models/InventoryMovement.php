<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    public const REASON_LABELS = [
        'manual_adjustment' => 'Manual adjustment',
        'order_placed' => 'Order placed',
        'order_cancelled' => 'Order cancelled',
        'order_reinstated' => 'Order reinstated',
        'return_received' => 'Return received',
        'exchange_received' => 'Exchange item received',
        'exchange_shipped' => 'Exchange replacement',
    ];

    protected $fillable = [
        'product_id',
        'size',
        'change',
        'stock_after',
        'reason',
        'reference_type',
        'reference_id',
        'note',
        'user_id',
    ];

    protected $casts = [
        'change' => 'integer',
        'stock_after' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASON_LABELS[$this->reason] ?? ucfirst(str_replace('_', ' ', $this->reason));
    }
}
