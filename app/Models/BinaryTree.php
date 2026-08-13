<?php

namespace App\Models;

use App\Enums\TreePosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BinaryTree extends Model
{
    protected $fillable = [
        'users_id',
        'parent_id',
        'position',
        'left_user_id',
        'right_user_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => TreePosition::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function leftUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'left_user_id');
    }

    public function rightUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'right_user_id');
    }
}
