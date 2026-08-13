<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\RenewalHistory;
use App\Models\User;
use App\Services\Renewals\RenewalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class RenewalController extends Controller
{
    public function active(): View
    {
        $warningDays = (int) config('citymax.membership.expiry_warning_days', 10);

        $users = User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->where('payment_status', true)
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->with('package')
            ->orderBy('expiry_date')
            ->paginate(25);

        return view('admin.renewals.active', compact('users', 'warningDays'));
    }

    public function renew(Request $request, int $user, RenewalService $renewals): RedirectResponse
    {
        $member = User::query()->findOrFail($user);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:255']]);

        try {
            $renewals->renew($member, $request->user(), $data['notes'] ?? null);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "User #{$member->id} renewed.");
    }

    public function renewed(): View
    {
        $rows = RenewalHistory::query()->with(['user', 'renewedBy'])->latest()->paginate(25);

        return view('admin.renewals.renewed', compact('rows'));
    }

    public function expired(): View
    {
        $users = User::query()
            ->where('role', UserRole::Customer)
            ->where(function ($q) {
                $q->whereDate('expiry_date', '<', now()->toDateString())
                    ->orWhere('status', 'expired');
            })
            ->orderByDesc('expiry_date')
            ->paginate(25);

        return view('admin.renewals.expired', compact('users'));
    }
}
