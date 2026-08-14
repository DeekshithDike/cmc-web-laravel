<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\RenewalHistory;
use App\Models\User;
use App\Services\Renewals\RenewalService;
use App\Support\AdminList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class RenewalController extends Controller
{
    public function active(Request $request): View
    {
        $warningDays = (int) config('citymax.membership.expiry_warning_days');
        $q = AdminList::search($request);

        $users = User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->where('payment_status', true)
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->tap(fn ($query) => AdminList::applySearch($query, $q, ['name', 'email', 'phone']))
            ->with('package:id,name')
            ->orderBy('expiry_date')
            ->paginate(AdminList::perPage($request))
            ->withQueryString();

        return view('admin.renewals.active', compact('users', 'warningDays', 'q'));
    }

    public function renew(Request $request, int $user, RenewalService $renewals): RedirectResponse
    {
        $member = User::query()->findOrFail($user);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:255']]);

        try {
            $renewals->renew($member, $request->user('admin'), $data['notes'] ?? null);
        } catch (InvalidArgumentException|Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "User #{$member->id} renewed.");
    }

    public function renewed(Request $request): View
    {
        $q = AdminList::search($request);

        $rows = RenewalHistory::query()
            ->with(['user:id,name,email', 'renewedBy:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                if (AdminList::isNumericId($q)) {
                    $query->where('user_id', (int) $q);

                    return;
                }

                $like = AdminList::like($q);
                $query->whereHas('user', function ($userQuery) use ($like) {
                    $userQuery->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->latest('id')
            ->paginate(AdminList::perPage($request))
            ->withQueryString();

        return view('admin.renewals.renewed', compact('rows', 'q'));
    }

    public function expired(Request $request): View
    {
        $q = AdminList::search($request);

        $users = User::query()
            ->where('role', UserRole::Customer)
            ->where(function ($query) {
                $query->whereDate('expiry_date', '<', now()->toDateString())
                    ->orWhere('status', 'expired');
            })
            ->tap(fn ($query) => AdminList::applySearch($query, $q, ['name', 'email', 'phone']))
            ->orderByDesc('expiry_date')
            ->paginate(AdminList::perPage($request))
            ->withQueryString();

        return view('admin.renewals.expired', compact('users', 'q'));
    }
}
