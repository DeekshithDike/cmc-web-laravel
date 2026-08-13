<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\Withdrawals\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class WithdrawalController extends Controller
{
    public function index(Request $request, string $status = 'pending'): View
    {
        $statusEnum = WithdrawalStatus::tryFrom($status) ?? WithdrawalStatus::Pending;

        $withdrawals = Withdrawal::query()
            ->with('user')
            ->where('status', $statusEnum)
            ->latest()
            ->paginate(25);

        return view('admin.withdrawals.index', [
            'withdrawals' => $withdrawals,
            'status' => $statusEnum,
        ]);
    }

    public function complete(Request $request, int $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:255']]);
        $model = Withdrawal::query()->findOrFail($withdrawal);

        try {
            $paid = $service->complete($model, $data['remarks'] ?? null);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $label = $paid->status?->label() ?? 'updated';

        return back()->with('success', "Withdrawal {$label} via ".($paid->payout_provider ?? config('payments.default_payout')).'.');
    }

    public function decline(Request $request, int $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:255']]);
        $model = Withdrawal::query()->findOrFail($withdrawal);

        try {
            $service->decline($model, $data['remarks'] ?? null);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Withdrawal declined and balance refunded (minus fee).');
    }
}
