<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDetail extends Model
{
    protected $fillable = [
        'user_id',
        'roi_amount',
        'binary_amount',
        'referral_amount',
        'total_amount',
        'paid_on',
    ];

    protected function casts(): array
    {
        return [
            'roi_amount' => 'decimal:2',
            'binary_amount' => 'decimal:2',
            'referral_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
