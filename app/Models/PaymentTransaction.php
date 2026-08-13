<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'package_id',
        'provider',
        'provider_ref',
        'amount',
        'currency',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
