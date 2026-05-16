<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'whatsapp_number',
        'role',
        'is_active',
        'avatar',
        'address',
        'google_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function adminRequests(): HasMany
    {
        return $this->hasMany(AdminRequest::class);
    }

    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }
    public function isAdmin(): bool      { return $this->role === 'admin'; }
    public function isCustomer(): bool   { return $this->role === 'customer'; }

    // backward compat: admin middleware checks isAdmin, now super_admin also passes
    public function hasAdminAccess(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }
}
