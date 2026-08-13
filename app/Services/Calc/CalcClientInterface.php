<?php

namespace App\Services\Calc;

interface CalcClientInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function placeMember(array $payload, bool $sync = false): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function dailyIncome(array $payload = [], bool $sync = false): array;
}
