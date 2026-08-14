<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ReferralIncome;
use App\Services\Business\BusinessVolumeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BusinessVolumeService $volumes): View
    {
        $user = $request->user('customer')->load('package:id,amount');
        $volume = $volumes->volumeForUser((int) $user->id);
        $today = now()->toDateString();

        $warningDays = (int) config('citymax.membership.expiry_warning_days');
        $daysLeft = null;
        $showExpiryWarning = false;

        if ($user->expiry_date) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($user->expiry_date->startOfDay(), false);
            $showExpiryWarning = $daysLeft >= 0 && $daysLeft <= $warningDays;
        }

        return view('customer.dashboard', [
            'user' => $user,
            'daysLeft' => $daysLeft,
            'showExpiryWarning' => $showExpiryWarning,
            'leftBusinessToday' => $volume['left_today'],
            'rightBusinessToday' => $volume['right_today'],
            'leftBusinessTotal' => $volume['left_total'],
            'rightBusinessTotal' => $volume['right_total'],
            'referralToday' => $this->referralDisplay((int) $user->id, $today),
            'referralTotal' => $this->referralDisplay((int) $user->id, null),
        ]);
    }

    private function referralDisplay(int $userId, ?string $onDate): string
    {
        $query = ReferralIncome::query()->where('user_id', $userId);
        if ($onDate) {
            $query->whereDate('earned_on', $onDate);
        }

        $volume = (float) $query->sum('amount');
        $percent = (float) config('citymax.income.referral_percent');
        $paid = $volume > 0 && $percent > 0
            ? round($volume * ($percent / 100), 2)
            : 0.0;

        return number_format($paid, 2, '.', '');
    }
}
