<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'customers'; // ✅ important

    protected $primaryKey = 'customer_id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'address',
        'zip',
        'state',
        'city',
        'status',
        'cart',
        'wishlist',
        'wallet_amount',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    // ✅ IMPORTANT: Address relation
    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id', 'customer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id', 'customer_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: 'Customer';
    }

    public function getCartItemsAttribute(): array
    {
        $raw = $this->cart;
        if (empty($raw) || $raw === 'null' || $raw === '[]') {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['items']) && is_array($decoded['items'])) {
            return $decoded['items'];
        }
        if (isset($decoded['cart']) && is_array($decoded['cart'])) {
            return $decoded['cart'];
        }

        return $decoded;
    }

    public function hasCartItems(): bool
    {
        return count($this->cart_items) > 0;
    }
}
