<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginService
{
    /**
     * Admin login with email + password.
     *
     * @throws ValidationException
     */
    public function attemptAdmin(string $email, string $password, bool $remember = false): User
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('email', $email)
            ->where('role', UserRole::Admin)
            ->first();

        if (! $this->passwordMatches($user, $password)) {
            throw ValidationException::withMessages([
                'email' => 'Invalid admin credentials.',
            ]);
        }

        Auth::login($user, $remember);
        request()->session()->regenerate();

        return $user;
    }

    /**
     * Customer login with member ID + password. Enforces active/paid/not-expired.
     *
     * @throws ValidationException
     */
    public function attemptCustomer(int|string $loginId, string $password, bool $remember = false): User
    {
        /** @var User|null $user */
        $user = User::query()
            ->whereKey($loginId)
            ->where('role', UserRole::Customer)
            ->first();

        if (! $this->passwordMatches($user, $password)) {
            throw ValidationException::withMessages([
                'login_id' => 'Invalid login ID or code.',
            ]);
        }

        if ($user->expiry_date && $user->expiry_date->copy()->endOfDay()->isPast()) {
            throw ValidationException::withMessages([
                'login_id' => 'This membership ID has expired. Please contact support for renewal.',
            ]);
        }

        if (! $user->is_active || ! $user->payment_status) {
            throw ValidationException::withMessages([
                'login_id' => 'This account is not active or payment is incomplete.',
            ]);
        }

        Auth::login($user, $remember);
        request()->session()->regenerate();

        return $user;
    }

    public function logout(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    private function passwordMatches(?User $user, string $password): bool
    {
        $hash = $user?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $matches = Hash::check($password, $hash);

        return $user !== null && $matches;
    }
}
