<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'status',
        'max_uses_per_user',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'value' => 'float',
        'max_uses_per_user' => 'integer',
    ];

    public static function findByCode($code)
    {
        return self::where('code', $code)->first();
    }

    public function discount($total)
    {
        if ($this->type == 'fixed') {
            return $this->value;
        }
        if ($this->type == 'percent') {
            return ($this->value / 100) * $total;
        }

        return 0;
    }

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
