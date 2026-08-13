<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OneTimeCredentials
{
    public function store(int $loginId, string $password, ?string $continueUrl = null): string
    {
        $token = Str::random(48);

        Cache::put($this->key($token), [
            'login_id' => $loginId,
            'password' => $password,
            'continue_url' => $continueUrl,
        ], now()->addMinutes(10));

        return $token;
    }

    /**
     * @return array{login_id:int,password:string,continue_url:?string}|null
     */
    public function pull(string $token): ?array
    {
        $payload = Cache::pull($this->key($token));

        return is_array($payload) ? $payload : null;
    }

    private function key(string $token): string
    {
        return 'one_time_credentials:'.$token;
    }
}
