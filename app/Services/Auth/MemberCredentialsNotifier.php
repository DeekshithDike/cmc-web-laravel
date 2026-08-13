<?php

namespace App\Services\Auth;

use App\Mail\MemberCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MemberCredentialsNotifier
{
    public function email(User $user): void
    {
        $password = (string) ($user->plain_password ?? '');
        if ($password === '' || ! filled($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new MemberCredentialsMail((int) $user->id, $password));
        } catch (Throwable $e) {
            Log::warning('Could not email member credentials', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
