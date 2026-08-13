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
        $user = $request->user()->load('package:id,amount');
        $volume = $volumes->volumeForUser((int) $user->id);
        $today = now()->toDateString();

        $warningDays = (int) config('citymax.membership.expiry_warning_days', 10);
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
            'referralToday' => number_format(
                (float) ReferralIncome::query()->where('user_id', $user->id)->whereDate('earned_on', $today)->sum('amount'),
                2,
                '.',
                ''
            ),
            'referralTotal' => number_format(
                (float) ReferralIncome::query()->where('user_id', $user->id)->sum('amount'),
                2,
                '.',
                ''
            ),
        ]);
    }
}
