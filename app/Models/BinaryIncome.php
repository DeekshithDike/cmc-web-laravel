<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinaryIncome extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'left_volume',
        'right_volume',
        'earned_on',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'left_volume' => 'decimal:2',
            'right_volume' => 'decimal:2',
            'earned_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
