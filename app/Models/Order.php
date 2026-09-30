<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'customer_id',
        'order_number',
        'sub_total',
        'quantity',
        'delivery_charge',
        'status',
        'total_amount',
        'first_name',
        'last_name',
        'country',
        'post_code',
        'address1',
        'address2',
        'phone',
        'email',
        'payment_method',
        'payment_status',
        'shipping_id',
        'coupon',
        'coupon_code',
        'city',
        'state',
        'expected_delivery_date',
        'delivered_at',          // NEW: actual delivery timestamp (source of truth for return window)
        'razorpay_order_id',
        'razorpay_payment_id',
        'order_type',
        'parent_order_id',
        'order_source',
        'payment_reference',
        'payment_proof',
        'paid_at',
        'admin_note',
    ];

    protected $casts = [
        'expected_delivery_date' => 'date',
        'delivered_at'           => 'datetime',
        'paid_at'                => 'datetime',
    ];

    public const PAYMENT_METHOD_LABELS = [
        'cod' => 'Cash on Delivery',
        'online' => 'Online (Razorpay)',
        'bank_transfer' => 'Bank transfer',
        'paypal' => 'PayPal',
    ];

    public const SOURCE_LABELS = [
        'website' => 'Website',
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram',
        'phone' => 'Phone call',
        'store' => 'In store',
    ];

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->payment_method] ?? ucfirst((string) $this->payment_method);
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCE_LABELS[$this->order_source ?? 'website'] ?? ucfirst((string) $this->order_source);
    }

    /** Bank-transfer order still waiting for the money to arrive. */
    public function isAwaitingBankPayment(): bool
    {
        return $this->payment_method === 'bank_transfer' && $this->payment_status !== 'paid';
    }

    protected $appends = ['can_update_address'];

    public function getCanUpdateAddressAttribute(): bool
    {
        if (!in_array($this->status, ['new', 'process'], true)) {
            return false;
        }

        if (!$this->created_at) {
            return false;
        }

        return $this->created_at->gt(now()->subHours(24));
    }

    public function cart_info()
    {
        return $this->hasMany(Cart::class, 'order_id', 'id');
    }

    public static function getAllOrder($id)
    {
        return Order::with('cart_info')->find($id);
    }

    public static function countActiveOrder()
    {
        $data = Order::count();
        return $data ?: 0;
    }

    public function cart()
    {
        return $this->hasMany(Cart::class);
    }

    public function shipping()
    {
        return $this->belongsTo(Shipping::class, 'shipping_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function returnRequest()
    {
        return $this->hasOne(ReturnOrder::class, 'order_number', 'order_number');
    }

    public function replacementOrder()
    {
        return $this->hasOne(Order::class, 'parent_order_id');
    }

    /**
     * Order this one was created to replace (for exchange replacement orders).
     */
    public function parentOrder()
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    /**
     * Whether this order is still within its return/exchange eligibility window.
     * Uses delivered_at (actual delivery) when available, falling back to the
     * estimated delivery date only if the real timestamp hasn't been recorded yet.
     */
    public function isWithinReturnWindow(int $days = 7): bool
    {
        if ($this->status !== 'delivered') {
            return false;
        }

        $deliveredDate = $this->delivered_at ?? $this->expected_delivery_date;

        if (!$deliveredDate) {
            return false;
        }

        $diffDays = now()->diffInDays($deliveredDate, false) * -1;

        return $diffDays >= 0 && $diffDays <= $days;
    }
}