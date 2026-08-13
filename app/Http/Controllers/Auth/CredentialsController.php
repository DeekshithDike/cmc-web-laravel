<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\OneTimeCredentials;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CredentialsController extends Controller
{
    public function show(string $token, OneTimeCredentials $credentials): View|Response
    {
        $payload = $credentials->pull($token);

        if ($payload === null) {
            return response()
                ->view('auth.credentials', ['payload' => null], 404);
        }

        return view('auth.credentials', ['payload' => $payload]);
    }
}
