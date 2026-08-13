<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerLoginController extends Controller
{
    public function __construct(private readonly LoginService $loginService) {}

    public function show(): View|RedirectResponse
    {
        if (auth()->check() && auth()->user()?->isCustomer()) {
            return redirect()->route('customer.dashboard');
        }

        return view('customer.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login_id' => ['required', 'integer'],
            'password' => ['required', 'string'],
        ]);

        $this->loginService->attemptCustomer(
            $credentials['login_id'],
            $credentials['password'],
            $request->boolean('remember'),
        );

        return redirect()->intended(route('customer.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->loginService->logout();

        return redirect()->route('customer.login');
    }
}
