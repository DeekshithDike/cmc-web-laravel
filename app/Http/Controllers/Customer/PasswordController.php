<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Support\MemberRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('customer.password.change');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:customer'],
            'password' => ['required', 'confirmed', MemberRules::assignedPassword()],
        ]);

        $user = $request->user('customer');
        $user->password = $data['password'];
        $user->save();

        return back()->with('success', 'Password updated successfully.');
    }
}
