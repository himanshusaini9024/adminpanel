<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password','role','photo','status','provider','provider_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function orders(){
        return $this->hasMany('App\Models\Order');
    }

    public function isStaff(): bool
    {
        return \App\Support\RoleAccess::isStaff($this);
    }

    public function isAdmin(): bool
    {
        return \App\Support\RoleAccess::isAdmin($this);
    }

    public function canModule(string $module): bool
    {
        return \App\Support\RoleAccess::canModule($this, $module);
    }

    public function roleLabel(): string
    {
        return \App\Support\RoleAccess::labels()[$this->role] ?? ucfirst((string) $this->role);
    }
}
