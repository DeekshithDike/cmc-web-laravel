<?php

namespace App\Services\Calc;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpCalcClient implements CalcClientInterface
{
    public function placeMember(array $payload, bool $sync = false): array
    {
        return $this->post('/internal/jobs/place-member', $payload, $sync);
    }

    public function dailyIncome(array $payload = [], bool $sync = false): array
    {
        return $this->post('/internal/jobs/daily-income', $payload, $sync);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, bool $sync): array
    {
        $base = rtrim((string) config('citymax.calc.base_url'), '/');
        $key = (string) config('citymax.calc.internal_api_key');

        try {
            $response = Http::timeout((int) config('citymax.calc.request_timeout', 30))
                ->withHeaders(['X-Internal-Api-Key' => $key])
                ->acceptJson()
                ->post($base.$path.($sync ? '?sync=1' : ''), $payload);

            $response->throw();

            return $response->json() ?? [];
        } catch (RequestException $e) {
            throw new RuntimeException('Calc service request failed: '.$e->getMessage(), 0, $e);
        }
    }
}
