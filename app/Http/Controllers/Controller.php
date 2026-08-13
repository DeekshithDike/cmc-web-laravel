<?php

namespace App\Http\Controllers;

use App\Services\Auth\OneTimeCredentials;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    protected function redirectToOneTimeCredentials(int $loginId, string $password, ?string $continueUrl = null): RedirectResponse
    {
        $token = app(OneTimeCredentials::class)->store($loginId, $password, $continueUrl);

        return redirect()->route('credentials.show', ['token' => $token]);
    }
}
