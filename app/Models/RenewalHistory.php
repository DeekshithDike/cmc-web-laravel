<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalHistory extends Model
{
    protected $table = 'renewal_histories';

    protected $fillable = [
        'user_id',
        'renewed_by',
        'previous_expiry',
        'new_expiry',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'previous_expiry' => 'date',
            'new_expiry' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewed_by');
    }
}
