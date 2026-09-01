<?php

namespace App\Http\Controllers\Customer;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\CarryForward;
use App\Models\PaymentDetail;
use App\Models\ReferralIncome;
use App\Models\Withdrawal;
use App\Services\Business\BusinessVolumeService;
use App\Support\CustomerPortal;
use App\Support\IncomeCalendar;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BusinessVolumeService $volumes): View
    {
        $user = CustomerPortal::member($request)->load(['package:id,name,amount']);
        $userId = (int) $user->id;
        $volume = $volumes->volumeForUser($userId);
        $today = IncomeCalendar::today();

        $warningDays = (int) config('citymax.membership.expiry_warning_days');
        $daysLeft = null;
        $showExpiryWarning = false;

        if ($user->expiry_date) {
            $daysLeft = IncomeCalendar::daysUntil($user->expiry_date);
            $showExpiryWarning = $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= $warningDays;
        }

        $income = $this->incomeSummary($userId, $today);
        $withdrawals = $this->withdrawalSummary($userId, $today);
        $carry = $this->latestCarry($userId);

        return view('customer.dashboard', [
            'user' => $user,
            'daysLeft' => $daysLeft,
            'showExpiryWarning' => $showExpiryWarning,
            'leftBusinessToday' => $volume['left_today'],
            'rightBusinessToday' => $volume['right_today'],
            'leftBusinessTotal' => $volume['left_total'],
            'rightBusinessTotal' => $volume['right_total'],
            'carryLeft' => $carry['left'],
            'carryRight' => $carry['right'],
            'referralToday' => $this->referralDisplay($userId, $today),
            'referralTotal' => $this->referralDisplay($userId, null),
            'earnedTotal' => $income['earned_total'],
            'roiLastPaid' => $income['roi_last_paid'],
            'roiLastPaidOn' => $income['roi_last_paid_on'],
            'roiTotal' => $income['roi_total'],
            'roiDays' => $income['roi_days'],
            'binaryToday' => $income['binary_today'],
            'binaryTotal' => $income['binary_total'],
            'lastIncome' => $income['last_income'],
            'lastIncomeOn' => $income['last_income_on'],
            'lastIncomeRoi' => $income['last_income_roi'],
            'lastIncomeBinary' => $income['last_income_binary'],
            'lastIncomeReferral' => $income['last_income_referral'],
            'withdrawnTotal' => $withdrawals['completed_amount'],
            'pendingWithdrawalAmount' => $withdrawals['pending_amount'],
            'pendingWithdrawalCount' => $withdrawals['pending_count'],
            'withdrawalsTodayCount' => $withdrawals['today_count'],
            'withdrawalsTodayAmount' => $withdrawals['today_amount'],
            'openRegisterUrl' => route('customer.register.open'),
            'openRegisterSponsoredUrl' => route('customer.register.open', [
                'sponsor' => encrypt((string) $user->id),
            ]),
        ]);
    }

    /**
     * @return array{
     *     earned_total: string,
     *     roi_total: string,
     *     roi_days: int,
     *     roi_last_paid: string,
     *     roi_last_paid_on: ?string,
     *     binary_total: string,
     *     binary_today: string,
     *     last_income: string,
     *     last_income_on: ?string,
     *     last_income_roi: string,
     *     last_income_binary: string,
     *     last_income_referral: string
     * }
     */
    private function incomeSummary(int $userId, string $today): array
    {
        $totals = PaymentDetail::query()
            ->where('user_id', $userId)
            ->selectRaw('COALESCE(SUM(roi_amount), 0) as total_roi')
            ->selectRaw('COALESCE(SUM(binary_amount), 0) as total_binary')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_earned')
            ->selectRaw('COALESCE(SUM(CASE WHEN roi_amount > 0 THEN 1 ELSE 0 END), 0) as roi_days')
            ->selectRaw('COALESCE(SUM(CASE WHEN paid_on = ? THEN binary_amount ELSE 0 END), 0) as binary_today', [$today])
            ->first();

        $lastRoi = PaymentDetail::query()
            ->where('user_id', $userId)
            ->where('roi_amount', '>', 0)
            ->orderByDesc('paid_on')
            ->first(['roi_amount', 'paid_on']);

        $lastDay = PaymentDetail::query()
            ->where('user_id', $userId)
            ->orderByDesc('paid_on')
            ->first(['roi_amount', 'binary_amount', 'referral_amount', 'total_amount', 'paid_on']);

        return [
            'earned_total' => $this->money($totals?->total_earned ?? 0),
            'roi_total' => $this->money($totals?->total_roi ?? 0),
            'roi_days' => (int) ($totals?->roi_days ?? 0),
            'roi_last_paid' => $this->money($lastRoi?->roi_amount ?? 0),
            'roi_last_paid_on' => $lastRoi?->paid_on ? IncomeCalendar::formatDate($lastRoi->paid_on) : null,
            'binary_total' => $this->money($totals?->total_binary ?? 0),
            'binary_today' => $this->money($totals?->binary_today ?? 0),
            'last_income' => $this->money($lastDay?->total_amount ?? 0),
            'last_income_on' => $lastDay?->paid_on ? IncomeCalendar::formatDate($lastDay->paid_on) : null,
            'last_income_roi' => $this->money($lastDay?->roi_amount ?? 0),
            'last_income_binary' => $this->money($lastDay?->binary_amount ?? 0),
            'last_income_referral' => $this->money($lastDay?->referral_amount ?? 0),
        ];
    }

    /**
     * @return array{
     *     completed_amount: string,
     *     pending_amount: string,
     *     pending_count: int,
     *     today_count: int,
     *     today_amount: string
     * }
     */
    private function withdrawalSummary(int $userId, string $today): array
    {
        [$start, $end] = IncomeCalendar::malaysiaDayUtcBounds($today);
        $completed = WithdrawalStatus::Completed->value;
        $pending = WithdrawalStatus::Pending->value;
        $processing = WithdrawalStatus::Processing->value;

        $row = Withdrawal::query()
            ->where('user_id', $userId)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as completed_amount,
                 COALESCE(SUM(CASE WHEN status IN (?, ?) THEN amount ELSE 0 END), 0) as pending_amount,
                 COALESCE(SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END), 0) as pending_count,
                 COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END), 0) as today_count,
                 COALESCE(SUM(CASE WHEN created_at >= ? AND created_at < ? THEN amount ELSE 0 END), 0) as today_amount',
                [$completed, $pending, $processing, $pending, $processing, $start, $end, $start, $end]
            )
            ->first();

        return [
            'completed_amount' => $this->money($row?->completed_amount ?? 0),
            'pending_amount' => $this->money($row?->pending_amount ?? 0),
            'pending_count' => (int) ($row?->pending_count ?? 0),
            'today_count' => (int) ($row?->today_count ?? 0),
            'today_amount' => $this->money($row?->today_amount ?? 0),
        ];
    }

    /**
     * @return array{left: string, right: string}
     */
    private function latestCarry(int $userId): array
    {
        $row = CarryForward::query()
            ->where('user_id', $userId)
            ->orderByDesc('as_of')
            ->orderByDesc('id')
            ->first(['left_carry', 'right_carry']);

        return [
            'left' => $this->money($row?->left_carry ?? 0),
            'right' => $this->money($row?->right_carry ?? 0),
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

        return $this->money($paid);
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
