<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerMembershipActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('customer');

        if (! $user) {
            return redirect()->route('customer.login');
        }

        if ($user->expiry_date && $user->expiry_date->copy()->endOfDay()->isPast()) {
            Auth::guard('customer')->logout();

            return redirect()
                ->route('customer.login')
                ->withErrors(['login_id' => 'This membership ID has expired. Please contact support for renewal.']);
        }

        if (! $user->is_active || ! $user->payment_status) {
            Auth::guard('customer')->logout();

            return redirect()
                ->route('customer.login')
                ->withErrors(['login_id' => 'This account is not active or payment is incomplete.']);
        }

        return $next($request);
    }
}
