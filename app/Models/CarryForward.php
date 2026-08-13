<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarryForward extends Model
{
    protected $fillable = [
        'user_id',
        'left_carry',
        'right_carry',
        'as_of',
    ];

    protected function casts(): array
    {
        return [
            'left_carry' => 'decimal:2',
            'right_carry' => 'decimal:2',
            'as_of' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
