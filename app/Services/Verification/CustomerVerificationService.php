<?php

namespace App\Services\Verification;

use App\Enums\UserRole;
use App\Models\BinaryIncome;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\CarryForward;
use App\Models\DailyIncomeRun;
use App\Models\PaymentDetail;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Support\AdminList;
use App\Support\IncomeCalendar;
use App\Support\VerificationFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerVerificationService
{
    public function searchCustomers(string $q, int $perPage, int $page = 1): LengthAwarePaginator
    {
        return User::query()
            ->where('role', UserRole::Customer)
            ->tap(fn ($query) => AdminList::applySearch($query, $q, ['name', 'email', 'phone']))
            ->with('package:id,name,amount')
            ->orderBy('id')
            ->paginate($perPage, ['id', 'name', 'email', 'package_id', 'is_active', 'payment_status', 'expiry_date'], 'page', $page)
            ->withQueryString();
    }

    public function report(User $user, VerificationFilters $filters, Request $request): CustomerVerificationReport
    {
        $today = IncomeCalendar::today();
        $createdOn = $user->created_at?->timezone(IncomeCalendar::timezone())->toDateString() ?: $today;
        [$from, $to] = $filters->period($today, $createdOn);
        if ($filters->range === 'all') {
            $from = $this->lifetimeStart((int) $user->id, $createdOn);
        }
        $perPage = AdminList::perPage($request);

        $user->loadMissing([
            'package:id,name,amount,roi_percent',
            'sponsor:id,name',
            'parent:id,name',
        ]);

        $eligibility = $this->eligibility($user, $today);
        $totals = $this->totals((int) $user->id, $from, $to);
        $carryNow = $this->latestCarry((int) $user->id, $to);
        $yesterday = IncomeCalendar::previousDate();
        $yesterdayCompleted = DailyIncomeRun::query()
            ->whereDate('as_of', $yesterday)
            ->where('status', DailyIncomeRun::STATUS_COMPLETED)
            ->exists();

        $answer = VerificationAnswer::make(
            $filters->focus,
            $filters->range,
            $eligibility['eligible'],
            $from <= $today && $to >= $today,
            $yesterdayCompleted,
            $totals,
            (float) config('citymax.income.binary_percent'),
            (float) config('citymax.income.referral_percent'),
            (float) ($user->package->amount ?? 0),
            (float) $carryNow['left'],
            (float) $carryNow['right'],
        );

        $dates = $this->ledgerDates((int) $user->id, $from, $to, $today, $filters->days);
        $ledger = $this->paginateDates($dates, $perPage, (int) $request->query('page', 1), $request);
        $ledger->setCollection($this->ledgerRows((int) $user->id, $ledger->getCollection(), $today, $carryNow));

        $sources = $filters->showsSources()
            ? $this->paginateSources((int) $user->id, $from, $to, $perPage)
            : null;
        $referrals = $filters->showsReferrals()
            ? $this->paginateReferrals((int) $user->id, $from, $to, $perPage, $today)
            : null;

        return new CustomerVerificationReport(
            user: $user,
            today: $today,
            from: $from,
            to: $to,
            eligibility: $eligibility,
            totals: $totals,
            answer: $answer,
            carryNow: $carryNow,
            yesterdayCompleted: $yesterdayCompleted,
            ledger: $ledger,
            sources: $sources,
            referrals: $referrals,
        );
    }

    /**
     * @return array{active: bool, paid: bool, expired: bool, eligible: bool}
     */
    private function eligibility(User $user, string $today): array
    {
        $expired = $user->expiry_date !== null && $user->expiry_date->toDateString() < $today;

        return [
            'active' => (bool) $user->is_active,
            'paid' => (bool) $user->payment_status,
            'expired' => $expired,
            'eligible' => (bool) $user->is_active && (bool) $user->payment_status && ! $expired,
        ];
    }

    /**
     * All dates is lifetime: include volume/pay rows dated before the Malaysia
     * created-on day (UTC join times after midnight Malaysia).
     */
    private function lifetimeStart(int $userId, string $createdOn): string
    {
        $earliest = $this->earliestActivityDate($userId);

        return $earliest !== null && $earliest < $createdOn ? $earliest : $createdOn;
    }

    private function earliestActivityDate(int $userId): ?string
    {
        $dates = collect([
            BinaryTreeLeft::query()->where('user_id', $userId)->min('business_date'),
            BinaryTreeRight::query()->where('user_id', $userId)->min('business_date'),
            PaymentDetail::query()->where('user_id', $userId)->min('paid_on'),
            ReferralIncome::query()->where('user_id', $userId)->min('earned_on'),
            BinaryIncome::query()->where('user_id', $userId)->min('earned_on'),
            CarryForward::query()->where('user_id', $userId)->min('as_of'),
        ])->filter(fn ($date) => $date !== null && $date !== '')
            ->map(fn ($date) => $date instanceof Carbon
                ? $date->toDateString()
                : Carbon::parse((string) $date)->toDateString());

        return $dates->isEmpty() ? null : $dates->min();
    }

    /**
     * @return array{left: float, right: float, matched: float, roi: float, binary: float, referral: float, match_days: int}
     */
    private function totals(int $userId, string $from, string $to): array
    {
        $left = (float) BinaryTreeLeft::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->sum('amount');
        $right = (float) BinaryTreeRight::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->sum('amount');

        $paid = PaymentDetail::query()
            ->where('user_id', $userId)
            ->whereDate('paid_on', '>=', $from)
            ->whereDate('paid_on', '<=', $to)
            ->selectRaw('COALESCE(SUM(roi_amount), 0) as roi')
            ->selectRaw('COALESCE(SUM(binary_amount), 0) as binary')
            ->selectRaw('COALESCE(SUM(referral_amount), 0) as referral')
            ->first();

        $matched = BinaryIncome::query()
            ->where('user_id', $userId)
            ->whereDate('earned_on', '>=', $from)
            ->whereDate('earned_on', '<=', $to)
            ->selectRaw('COALESCE(SUM(CASE WHEN left_volume < right_volume THEN left_volume ELSE right_volume END), 0) as matched')
            ->selectRaw('COALESCE(SUM(CASE WHEN (CASE WHEN left_volume < right_volume THEN left_volume ELSE right_volume END) > 0 THEN 1 ELSE 0 END), 0) as match_days')
            ->first();

        return [
            'left' => $left,
            'right' => $right,
            'matched' => (float) ($matched->matched ?? 0),
            'roi' => (float) ($paid->roi ?? 0),
            'binary' => (float) ($paid->binary ?? 0),
            'referral' => (float) ($paid->referral ?? 0),
            'match_days' => (int) ($matched->match_days ?? 0),
        ];
    }

    /**
     * @return array{left: string, right: string}
     */
    private function latestCarry(int $userId, string $asOf): array
    {
        $row = CarryForward::query()
            ->where('user_id', $userId)
            ->whereDate('as_of', '<=', $asOf)
            ->orderByDesc('as_of')
            ->first(['left_carry', 'right_carry']);

        return [
            'left' => number_format((float) ($row->left_carry ?? 0), 2, '.', ''),
            'right' => number_format((float) ($row->right_carry ?? 0), 2, '.', ''),
        ];
    }

    /**
     * @return list<string>
     */
    private function ledgerDates(int $userId, string $from, string $to, string $today, string $mode): array
    {
        if ($mode === 'all') {
            return $this->calendarDates($from, $to);
        }

        if ($mode === 'match') {
            return BinaryIncome::query()
                ->where('user_id', $userId)
                ->whereDate('earned_on', '>=', $from)
                ->whereDate('earned_on', '<=', $to)
                ->orderByDesc('earned_on')
                ->pluck('earned_on')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->unique()
                ->values()
                ->all();
        }

        $dates = $this->activityDates($userId, $from, $to, $mode === 'zero');

        if ($from <= $today && $to >= $today && ! in_array($today, $dates, true) && $mode !== 'zero' && $mode !== 'match') {
            array_unshift($dates, $today);
        }

        rsort($dates);

        return array_values(array_unique($dates));
    }

    /**
     * @return list<string>
     */
    private function calendarDates(string $from, string $to): array
    {
        $dates = [];
        $cursor = Carbon::parse($to, IncomeCalendar::timezone())->startOfDay();
        $start = Carbon::parse($from, IncomeCalendar::timezone())->startOfDay();

        while ($cursor->gte($start)) {
            $dates[] = $cursor->toDateString();
            $cursor->subDay();
        }

        return $dates;
    }

    /**
     * @return list<string>
     */
    private function activityDates(int $userId, string $from, string $to, bool $zeroIncomeOnly): array
    {
        $left = DB::table('binary_tree_lefts')
            ->select('business_date as d')
            ->where('user_id', $userId)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);
        $right = DB::table('binary_tree_rights')
            ->select('business_date as d')
            ->where('user_id', $userId)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);
        $paid = DB::table('payment_details')
            ->select('paid_on as d')
            ->where('user_id', $userId)
            ->whereDate('paid_on', '>=', $from)
            ->whereDate('paid_on', '<=', $to);
        $carry = DB::table('carry_forwards')
            ->select('as_of as d')
            ->where('user_id', $userId)
            ->whereDate('as_of', '>=', $from)
            ->whereDate('as_of', '<=', $to);
        $referral = DB::table('referral_incomes')
            ->select('earned_on as d')
            ->where('user_id', $userId)
            ->whereDate('earned_on', '>=', $from)
            ->whereDate('earned_on', '<=', $to);

        $dates = DB::query()
            ->fromSub($left->union($right)->union($paid)->union($carry)->union($referral), 'activity_dates')
            ->select('d')
            ->whereNotNull('d')
            ->distinct()
            ->orderByDesc('d')
            ->pluck('d')
            ->map(fn ($date) => Carbon::parse((string) $date)->toDateString())
            ->all();

        if (! $zeroIncomeOnly) {
            return $dates;
        }

        $paidTotals = PaymentDetail::query()
            ->where('user_id', $userId)
            ->whereDate('paid_on', '>=', $from)
            ->whereDate('paid_on', '<=', $to)
            ->get(['paid_on', 'roi_amount', 'binary_amount', 'referral_amount'])
            ->keyBy(fn ($row) => $row->paid_on?->toDateString());

        return array_values(array_filter($dates, function (string $date) use ($paidTotals) {
            $row = $paidTotals->get($date);
            if ($row === null) {
                return true;
            }

            return (float) $row->roi_amount <= 0
                && (float) $row->binary_amount <= 0
                && (float) $row->referral_amount <= 0;
        }));
    }

    /**
     * @param  list<string>  $dates
     * @return LengthAwarePaginator<int, string>
     */
    private function paginateDates(array $dates, int $perPage, int $page, Request $request): LengthAwarePaginator
    {
        $page = max(1, $page);
        $total = count($dates);
        $slice = array_slice($dates, ($page - 1) * $perPage, $perPage);

        return (new Paginator($slice, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
            'pageName' => 'page',
        ]))->withQueryString();
    }

    /**
     * @param  Collection<int, string>  $dates
     * @param  array{left: string, right: string}  $fallbackCarry
     * @return Collection<int, array<string, mixed>>
     */
    private function ledgerRows(int $userId, Collection $dates, string $today, array $fallbackCarry): Collection
    {
        if ($dates->isEmpty()) {
            return collect();
        }

        $from = $dates->min();
        $to = $dates->max();
        // Page dates are newest-first; min/max still work on Y-m-d strings.

        $lefts = BinaryTreeLeft::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->selectRaw('business_date, SUM(amount) as total')
            ->groupBy('business_date')
            ->pluck('total', 'business_date');
        $rights = BinaryTreeRight::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->selectRaw('business_date, SUM(amount) as total')
            ->groupBy('business_date')
            ->pluck('total', 'business_date');
        $payments = PaymentDetail::query()
            ->where('user_id', $userId)
            ->whereDate('paid_on', '>=', $from)
            ->whereDate('paid_on', '<=', $to)
            ->get()
            ->keyBy(fn (PaymentDetail $row) => $row->paid_on?->toDateString());
        $matches = BinaryIncome::query()
            ->where('user_id', $userId)
            ->whereDate('earned_on', '>=', $from)
            ->whereDate('earned_on', '<=', $to)
            ->get()
            ->keyBy(fn (BinaryIncome $row) => $row->earned_on?->toDateString());
        $carries = CarryForward::query()
            ->where('user_id', $userId)
            ->whereDate('as_of', '<=', $to)
            ->orderBy('as_of')
            ->get(['as_of', 'left_carry', 'right_carry']);

        return $dates->map(function (string $date) use ($today, $lefts, $rights, $payments, $matches, $carries, $fallbackCarry) {
            $pending = $date === $today;
            $left = $this->pluckDate($lefts, $date);
            $right = $this->pluckDate($rights, $date);
            $payment = $payments->get($date);
            $match = $matches->get($date);
            $matched = $pending
                ? null
                : ($match
                    ? (float) min((float) $match->left_volume, (float) $match->right_volume)
                    : 0.0);
            $roi = $pending ? null : (float) ($payment->roi_amount ?? 0);
            $binary = $pending ? null : (float) ($payment->binary_amount ?? 0);
            $referral = $pending ? null : (float) ($payment->referral_amount ?? 0);
            $carry = $this->carryOn($carries, $date, $fallbackCarry);

            return [
                'date' => $date,
                'day' => Carbon::parse($date, IncomeCalendar::timezone())->format('D'),
                'left' => $left,
                'right' => $right,
                'matched' => $matched,
                'carry_left' => $carry['left'],
                'carry_right' => $carry['right'],
                'roi' => $roi,
                'binary' => $binary,
                'referral' => $referral,
                'paid' => $pending ? null : round((float) $roi + (float) $binary + (float) $referral, 2),
                'pending' => $pending,
            ];
        });
    }

    /**
     * @param  Collection<string|int, mixed>  $pluck
     */
    private function pluckDate(Collection $pluck, string $date): float
    {
        foreach ($pluck as $key => $value) {
            $keyDate = $key instanceof Carbon ? $key->toDateString() : Carbon::parse((string) $key)->toDateString();
            if ($keyDate === $date) {
                return (float) $value;
            }
        }

        return 0.0;
    }

    /**
     * @param  Collection<int, CarryForward>  $carries
     * @param  array{left: string, right: string}  $fallback
     * @return array{left: string, right: string}
     */
    private function carryOn(Collection $carries, string $date, array $fallback): array
    {
        $chosen = null;
        foreach ($carries as $row) {
            $asOf = $row->as_of?->toDateString();
            if ($asOf === null || $asOf > $date) {
                continue;
            }
            $chosen = $row;
            if ($asOf === $date) {
                break;
            }
        }

        if ($chosen === null) {
            return $fallback;
        }

        return [
            'left' => number_format((float) $chosen->left_carry, 2, '.', ''),
            'right' => number_format((float) $chosen->right_carry, 2, '.', ''),
        ];
    }

    private function paginateSources(int $userId, string $from, string $to, int $perPage): LengthAwarePaginator
    {
        $left = DB::table('binary_tree_lefts')
            ->select('business_date as source_date', DB::raw("'Left' as side"), 'from_user_id', 'amount')
            ->where('user_id', $userId)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);
        $right = DB::table('binary_tree_rights')
            ->select('business_date as source_date', DB::raw("'Right' as side"), 'from_user_id', 'amount')
            ->where('user_id', $userId)
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);

        return DB::query()
            ->fromSub($left->unionAll($right), 'volume_sources')
            ->leftJoin('users', 'users.id', '=', 'volume_sources.from_user_id')
            ->select(
                'volume_sources.source_date',
                'volume_sources.side',
                'volume_sources.from_user_id',
                'volume_sources.amount',
                'users.name as from_name',
                'users.created_at as from_activated_at'
            )
            ->orderByDesc('volume_sources.source_date')
            ->orderBy('volume_sources.side')
            ->paginate($perPage, ['*'], 'sources_page')
            ->withQueryString();
    }

    private function paginateReferrals(int $userId, string $from, string $to, int $perPage, string $today): LengthAwarePaginator
    {
        $percent = (float) config('citymax.income.referral_percent');
        $paginator = ReferralIncome::query()
            ->where('user_id', $userId)
            ->whereDate('earned_on', '>=', $from)
            ->whereDate('earned_on', '<=', $to)
            ->with('fromUser:id,name,created_at')
            ->orderByDesc('earned_on')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'referrals_page')
            ->withQueryString();

        $earnedDates = $paginator->getCollection()
            ->map(fn (ReferralIncome $row) => $row->earned_on?->toDateString())
            ->filter()
            ->unique()
            ->values();

        $paidByDate = $earnedDates->isEmpty()
            ? collect()
            : PaymentDetail::query()
                ->where('user_id', $userId)
                ->whereIn('paid_on', $earnedDates->all())
                ->get(['paid_on', 'referral_amount'])
                ->keyBy(fn (PaymentDetail $row) => $row->paid_on?->toDateString());

        return $paginator->through(function (ReferralIncome $row) use ($percent, $paidByDate, $today) {
            $date = $row->earned_on?->toDateString();
            $stored = (float) $row->amount;
            $expected = $stored > 0 && $percent > 0
                ? round($stored * ($percent / 100), 2)
                : 0.0;
            $dayPaid = (float) ($paidByDate->get($date)?->referral_amount ?? 0);
            $status = $date === $today
                ? 'Pending daily run'
                : ($dayPaid > 0 ? 'Included in that day’s run' : 'Not paid on that day’s run');

            return [
                'date' => $date,
                'activated_at' => IncomeCalendar::formatWhen($row->fromUser?->created_at, $row->earned_on),
                'from_id' => $row->from_user_id,
                'from_name' => $row->fromUser?->name,
                'stored' => $stored,
                'expected' => $expected,
                'status' => $status,
            ];
        });
    }
}
