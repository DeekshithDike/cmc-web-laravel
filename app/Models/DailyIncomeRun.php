<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyIncomeRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'as_of',
        'status',
        'triggered_by',
        'processed',
        'total_paid',
    ];

    protected function casts(): array
    {
        return [
            'as_of' => 'date',
            'processed' => 'integer',
            'total_paid' => 'decimal:2',
        ];
    }
}
