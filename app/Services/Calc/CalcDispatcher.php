<?php

namespace App\Services\Calc;

use App\Models\CalculationJob;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches calc jobs and records audit rows. Never blocks membership on calc outage.
 */
class CalcDispatcher
{
    public function __construct(private readonly CalcClientInterface $client)
    {
    }

    public function placeMember(array $payload): CalculationJob
    {
        return $this->dispatch('place-member', $payload, fn () => $this->client->placeMember($payload, false));
    }

    public function dailyIncome(array $payload = []): CalculationJob
    {
        return $this->dispatch('daily-income', $payload, fn () => $this->client->dailyIncome($payload, false));
    }

    /**
     * @param  callable(): array<string, mixed>  $call
     */
    private function dispatch(string $type, array $payload, callable $call): CalculationJob
    {
        $job = CalculationJob::query()->create([
            'job_type' => $type,
            'status' => 'queued',
            'payload' => $payload,
            'started_at' => now(),
        ]);

        try {
            $result = $call();
            $job->fill([
                'status' => 'completed',
                'external_id' => (string) ($result['id'] ?? $result['jobId'] ?? ''),
                'result' => $result,
                'finished_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Calc dispatch failed', ['type' => $type, 'error' => $e->getMessage()]);
            $job->fill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();
        }

        return $job->fresh();
    }
}
