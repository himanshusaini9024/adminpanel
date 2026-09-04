<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushToken extends Model
{
    use HasFactory;
    protected $fillable = [
        'customer_id',
        'token',
        'p256dh',
        'auth',
        'device_type',
        'browser',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];
}
