<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PaymentDetail;
use App\Models\ReferralIncome;
use App\Services\Business\BusinessVolumeService;
use App\Support\IncomeCalendar;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BusinessVolumeService $volumes): View
    {
        $user = $request->user('customer')->load('package:id,amount');
        $volume = $volumes->volumeForUser((int) $user->id);
        $today = IncomeCalendar::today();

        $warningDays = (int) config('citymax.membership.expiry_warning_days');
        $daysLeft = null;
        $showExpiryWarning = false;

        if ($user->expiry_date) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($user->expiry_date->startOfDay(), false);
            $showExpiryWarning = $daysLeft >= 0 && $daysLeft <= $warningDays;
        }

        $roi = $this->roiWallet((int) $user->id);

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
            'roiLastPaid' => $roi['last_paid'],
            'roiLastPaidOn' => $roi['last_paid_on'],
            'roiTotal' => $roi['total'],
            'roiDays' => $roi['days'],
            'openRegisterUrl' => route('customer.register.open'),
            'openRegisterSponsoredUrl' => route('customer.register.open', [
                'sponsor' => encrypt((string) $user->id),
            ]),
        ]);
    }

    /**
     * @return array{last_paid: string, last_paid_on: ?string, total: string, days: int}
     */
    private function roiWallet(int $userId): array
    {
        $last = PaymentDetail::query()
            ->where('user_id', $userId)
            ->where('roi_amount', '>', 0)
            ->orderByDesc('paid_on')
            ->first(['roi_amount', 'paid_on']);

        $totals = PaymentDetail::query()
            ->where('user_id', $userId)
            ->selectRaw('COALESCE(SUM(roi_amount), 0) as total_roi')
            ->selectRaw('COALESCE(SUM(CASE WHEN roi_amount > 0 THEN 1 ELSE 0 END), 0) as roi_days')
            ->first();

        return [
            'last_paid' => number_format((float) ($last->roi_amount ?? 0), 2, '.', ''),
            'last_paid_on' => $last?->paid_on ? IncomeCalendar::formatDate($last->paid_on) : null,
            'total' => number_format((float) ($totals->total_roi ?? 0), 2, '.', ''),
            'days' => (int) ($totals->roi_days ?? 0),
        ];
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
