<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralIncome extends Model
{
    protected $fillable = [
        'user_id',
        'from_user_id',
        'amount',
        'earned_on',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'earned_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}
