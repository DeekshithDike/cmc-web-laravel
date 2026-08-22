<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\Withdrawals\WithdrawalService;
use App\Support\CustomerPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class WithdrawalController extends Controller
{
    public function create(Request $request): View
    {
        return view('customer.withdrawals.create', [
            'user' => $request->user('customer'),
            'minimum' => config('citymax.withdrawal.minimum'),
            'fee' => config('citymax.withdrawal.fee'),
        ]);
    }

    public function store(Request $request, WithdrawalService $service): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'wallet_address' => ['required', 'string', 'max:128'],
        ]);

        try {
            $service->request($request->user('customer'), (float) $data['amount'], $data['wallet_address']);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('customer.withdrawals.history')->with('success', 'Withdrawal request submitted.');
    }

    public function history(Request $request): View
    {
        $withdrawals = Withdrawal::query()
            ->where('user_id', CustomerPortal::member($request)->id)
            ->latest()
            ->paginate(25);

        return view('customer.withdrawals.history', compact('withdrawals'));
    }
}
