<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinaryTreeLeft extends Model
{
    protected $table = 'binary_tree_lefts';

    protected $fillable = [
        'user_id',
        'from_user_id',
        'amount',
        'business_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'business_date' => 'date',
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
