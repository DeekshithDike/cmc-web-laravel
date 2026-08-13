<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalculationJob extends Model
{
    protected $fillable = [
        'job_type',
        'external_id',
        'status',
        'payload',
        'result',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
