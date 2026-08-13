<?php

namespace App\Models;

use App\Enums\TreePosition;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'is_active',
        'payment_status',
        'is_power_id',
        'sponsor_id',
        'parent_id',
        'position',
        'package_id',
        'expiry_date',
        'phone',
        'country',
        'wallet_address',
        'wallet_balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'position' => TreePosition::class,
            'is_active' => 'boolean',
            'payment_status' => 'boolean',
            'is_power_id' => 'boolean',
            'expiry_date' => 'date',
            'wallet_balance' => 'decimal:2',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    public function membershipIsActive(): bool
    {
        if (! $this->is_active || ! $this->payment_status) {
            return false;
        }

        if ($this->expiry_date === null) {
            return true;
        }

        return $this->expiry_date->copy()->endOfDay()->isFuture()
            || $this->expiry_date->isToday();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function binaryTree(): HasOne
    {
        return $this->hasOne(BinaryTree::class, 'users_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function paymentDetails(): HasMany
    {
        return $this->hasMany(PaymentDetail::class);
    }
}
