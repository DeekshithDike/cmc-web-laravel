<?php

namespace App\Services\Income;

use App\Enums\UserRole;
use App\Models\BinaryIncome;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\CarryForward;
use App\Models\DailyIncomeRun;
use App\Models\PaymentDetail;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use App\Support\IncomeCalendar;
use App\Support\PostgresIdSequences;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Production repair for the 16 Aug 2026 daily run that never fired.
 *
 * Does not call income:daily for that date (that would pay members who joined later).
 * Only members created on or before 16 Aug (or with 16 Aug volume/referral) are touched.
 *
 * Reads the live database, not a frozen backup: later daily runs, activations, volume,
 * and withdrawals after the dump are included. Already-paid binary/referral is never
 * reduced; only missing deltas are credited.
 */
class RepairMissed16AugIncomeService
{
    public const AS_OF = '2026-08-16';

    public function __construct(private readonly WalletService $wallet)
    {
    }

    /**
     * When the Malaysia midnight cron would have written 16 Aug (17 Aug 00:00 MYT).
     */
    public function cronAt(): Carbon
    {
        return Carbon::parse('2026-08-17 00:00:00', IncomeCalendar::timezone())->utc();
    }

    /**
     * @return array{status: string, lines: list<string>, credits: list<array{user_id: int, amount: string, reason: string, at: string, paid_on: string}>}
     */
    public function inspect(): array
    {
        $plan = $this->plan();
        $unpaid = array_values(array_filter(
            $plan['credits'],
            fn (array $credit) => (float) $credit['amount'] > 0
        ));
        $runDone = DailyIncomeRun::query()
            ->whereDate('as_of', self::AS_OF)
            ->where('status', DailyIncomeRun::STATUS_COMPLETED)
            ->exists();

        if ($runDone && $unpaid === []) {
            return ['status' => 'applied', 'lines' => $this->currentSummary(), 'credits' => []];
        }

        return [
            'status' => 'pending',
            'lines' => $plan['lines'],
            'credits' => $unpaid,
        ];
    }

    /**
     * @return array{status: string, lines: list<string>}
     */
    public function apply(): array
    {
        $state = $this->inspect();
        if ($state['status'] === 'applied') {
            return $state;
        }

        $plan = $this->plan();

        return DB::transaction(function () use ($plan) {
            PostgresIdSequences::sync();
            $cronAt = $this->cronAt();
            $binaryPercent = (float) config('citymax.income.binary_percent');
            $referralPercent = (float) config('citymax.income.referral_percent');
            $walletsTouched = [];

            $this->writeMissedDayRun($plan['cohort'], $cronAt, $binaryPercent, $referralPercent, $walletsTouched);

            foreach ($plan['replay_user_ids'] as $userId) {
                $this->replayBinaryAfterMissedDay($userId, $binaryPercent, $walletsTouched);
            }

            foreach (array_keys($walletsTouched) as $userId) {
                $this->rebuildWallet($userId);
            }

            $after = $this->inspect();
            if ($after['status'] !== 'applied') {
                throw new RuntimeException("Repair did not land in the applied state.\n".implode("\n", $after['lines']));
            }

            return ['status' => 'applied', 'lines' => $after['lines']];
        });
    }

    /**
     * @return array{
     *     lines: list<string>,
     *     credits: list<array{user_id: int, amount: string, reason: string, at: string, paid_on: string}>,
     *     cohort: list<User>,
     *     replay_user_ids: list<int>
     * }
     */
    private function plan(): array
    {
        $cohort = $this->cohort();
        if ($cohort === []) {
            throw new RuntimeException('No members created on or before '.self::AS_OF.' to repair.');
        }

        $binaryPercent = (float) config('citymax.income.binary_percent');
        $referralPercent = (float) config('citymax.income.referral_percent');
        $cronAt = $this->cronAt()->toDateTimeString();
        $lines = [
            'Missed daily run: '.self::AS_OF.' (Sunday — ROI $0, binary and referral still apply).',
            'Will insert daily_income_runs as cron at 17 Aug 00:00 Malaysia, not as a new repair date.',
            'Members in scope: '.count($cohort).' (created on or before '.self::AS_OF.', or with 16 Aug volume/referral). Later joiners are not paid for 16 Aug.',
        ];
        $credits = [];
        $replay = [];

        foreach ($cohort as $user) {
            $left = $this->sideVolume($user->id, BinaryTreeLeft::class, self::AS_OF);
            $right = $this->sideVolume($user->id, BinaryTreeRight::class, self::AS_OF);
            $referralVolume = $this->referralVolume($user->id, self::AS_OF);
            $referralPay = $this->percentOf($referralVolume, $referralPercent);
            $matched = min($left, $right);
            $binaryPay = $this->binaryPay($matched, $binaryPercent, (float) ($user->package->amount ?? 0));
            $leftCarry = round($left - $matched, 2);
            $rightCarry = round($right - $matched, 2);

            $existing16 = PaymentDetail::query()
                ->where('user_id', $user->id)
                ->whereDate('paid_on', self::AS_OF)
                ->first();
            $refDelta = round($referralPay - (float) ($existing16->referral_amount ?? 0), 2);
            $binDelta = round($binaryPay - (float) ($existing16->binary_amount ?? 0), 2);

            $lines[] = sprintf(
                '  %d %s — 16 Aug left $%s right $%s match $%s binary $%s referral $%s carry L $%s / R $%s',
                $user->id,
                $user->name,
                $this->money($left),
                $this->money($right),
                $this->money($matched),
                $this->money($binaryPay),
                $this->money($referralPay),
                $this->money($leftCarry),
                $this->money($rightCarry),
            );

            if ($refDelta > 0) {
                $credits[] = [
                    'user_id' => $user->id,
                    'amount' => $this->money($refDelta),
                    'reason' => 'daily_referral',
                    'at' => $cronAt,
                    'paid_on' => self::AS_OF,
                ];
            }
            if ($binDelta > 0) {
                $credits[] = [
                    'user_id' => $user->id,
                    'amount' => $this->money($binDelta),
                    'reason' => 'daily_binary',
                    'at' => $cronAt,
                    'paid_on' => self::AS_OF,
                ];
            }

            $replay[] = $user->id;
            $later = $this->previewReplay($user, $leftCarry, $rightCarry, $binaryPercent);
            foreach ($later['lines'] as $line) {
                $lines[] = $line;
            }
            foreach ($later['credits'] as $credit) {
                $credits[] = $credit;
            }
        }

        return [
            'lines' => $lines,
            'credits' => $credits,
            'cohort' => $cohort,
            'replay_user_ids' => $replay,
        ];
    }

    /**
     * @return list<User>
     */
    private function cohort(): array
    {
        $fromActivity = DB::query()
            ->fromSub(
                DB::table('binary_tree_lefts')->select('user_id')->whereDate('business_date', self::AS_OF)
                    ->union(DB::table('binary_tree_rights')->select('user_id')->whereDate('business_date', self::AS_OF))
                    ->union(DB::table('referral_incomes')->select('user_id')->whereDate('earned_on', self::AS_OF)),
                'activity_users'
            )
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return User::query()
            ->where('role', UserRole::Customer)
            ->where('is_power_id', false)
            ->where(function ($query) use ($fromActivity) {
                $query->whereDate('created_at', '<=', self::AS_OF);
                if ($fromActivity !== []) {
                    $query->orWhereIn('id', $fromActivity);
                }
            })
            ->with('package:id,amount')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @param  list<User>  $cohort
     * @param  array<int, true>  $walletsTouched
     */
    private function writeMissedDayRun(array $cohort, Carbon $cronAt, float $binaryPercent, float $referralPercent, array &$walletsTouched): void
    {
        $totalPaid = '0.00';
        $processed = 0;

        foreach ($cohort as $user) {
            $left = $this->sideVolume($user->id, BinaryTreeLeft::class, self::AS_OF);
            $right = $this->sideVolume($user->id, BinaryTreeRight::class, self::AS_OF);
            $referralVolume = $this->referralVolume($user->id, self::AS_OF);
            $referralPay = $this->percentOf($referralVolume, $referralPercent);
            $matched = min($left, $right);
            $binaryPay = $this->binaryPay($matched, $binaryPercent, (float) ($user->package->amount ?? 0));
            $roi = '0.00';
            $binary = $this->money($binaryPay);
            $referral = $this->money($referralPay);
            $total = bcadd(bcadd($roi, $binary, 2), $referral, 2);

            $existing = PaymentDetail::query()
                ->where('user_id', $user->id)
                ->whereDate('paid_on', self::AS_OF)
                ->first();

            if ($existing) {
                $this->topUpPayment(
                    $existing,
                    $user,
                    $binaryPay,
                    $referralPay,
                    $left,
                    $right,
                    $cronAt,
                    $walletsTouched
                );
                $totalPaid = bcadd($totalPaid, (string) $existing->fresh()->total_amount, 2);
            } else {
                $payment = new PaymentDetail([
                    'user_id' => $user->id,
                    'roi_amount' => $roi,
                    'binary_amount' => $binary,
                    'referral_amount' => $referral,
                    'total_amount' => $total,
                    'paid_on' => self::AS_OF,
                ]);
                $payment->created_at = $cronAt;
                $payment->updated_at = $cronAt;
                $payment->save();

                if ($binaryPay > 0) {
                    $this->writeBinaryIncome($user->id, self::AS_OF, $binaryPay, $left, $right, $cronAt);
                    $this->creditAt($user, $binaryPay, 'daily_binary', $cronAt, $walletsTouched);
                }

                if ($referralPay > 0) {
                    $this->creditAt($user, $referralPay, 'daily_referral', $cronAt, $walletsTouched);
                }

                $totalPaid = bcadd($totalPaid, $total, 2);
            }

            if ($left > 0 || $right > 0 || $referralPay > 0 || $binaryPay > 0) {
                $this->upsertCarry(
                    $user->id,
                    self::AS_OF,
                    round($left - $matched, 2),
                    round($right - $matched, 2),
                    $cronAt
                );
            }

            $processed++;
        }

        $run = DailyIncomeRun::query()->whereDate('as_of', self::AS_OF)->first();
        if (! $run) {
            $run = new DailyIncomeRun(['as_of' => self::AS_OF]);
        }
        $run->fill([
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'cron',
            'processed' => $processed,
            'total_paid' => $totalPaid,
        ]);
        $run->created_at = $cronAt;
        $run->updated_at = $cronAt;
        $run->save();
    }

    /**
     * @param  array<int, true>  $walletsTouched
     */
    private function replayBinaryAfterMissedDay(int $userId, float $binaryPercent, array &$walletsTouched): void
    {
        $user = User::query()->with('package:id,amount')->findOrFail($userId);
        $packageCap = (float) ($user->package->amount ?? 0);
        $carry = CarryForward::query()->where('user_id', $userId)->whereDate('as_of', self::AS_OF)->first();
        $leftCarry = (float) ($carry->left_carry ?? 0);
        $rightCarry = (float) ($carry->right_carry ?? 0);

        $dates = DailyIncomeRun::query()
            ->where('status', DailyIncomeRun::STATUS_COMPLETED)
            ->whereDate('as_of', '>', self::AS_OF)
            ->orderBy('as_of')
            ->get();

        foreach ($dates as $run) {
            $asOf = $run->as_of?->toDateString();
            if ($asOf === null) {
                continue;
            }

            $leftToday = $this->sideVolume($userId, BinaryTreeLeft::class, $asOf);
            $rightToday = $this->sideVolume($userId, BinaryTreeRight::class, $asOf);
            $left = $leftToday + $leftCarry;
            $right = $rightToday + $rightCarry;
            $matched = min($left, $right);
            $pay = $this->binaryPay($matched, $binaryPercent, $packageCap);
            $leftCarry = round($left - $matched, 2);
            $rightCarry = round($right - $matched, 2);
            $at = $run->created_at ?? $this->cronAt();

            $existing = PaymentDetail::query()
                ->where('user_id', $userId)
                ->whereDate('paid_on', $asOf)
                ->first();

            if ($existing) {
                $already = (float) $existing->binary_amount;
                $delta = round($pay - $already, 2);
                if ($delta > 0) {
                    $existing->binary_amount = $this->money($pay);
                    $existing->total_amount = bcadd(
                        bcadd((string) $existing->roi_amount, $this->money($pay), 2),
                        (string) $existing->referral_amount,
                        2
                    );
                    $existing->updated_at = $at;
                    $existing->save();
                    $this->writeBinaryIncome($userId, $asOf, $pay, $left, $right, $at);
                    $this->creditAt($user, $delta, 'daily_binary', $at, $walletsTouched);
                }
            }

            if ($left > 0 || $right > 0 || $pay > 0) {
                $this->upsertCarry($userId, $asOf, $leftCarry, $rightCarry);
            }
        }
    }

    /**
     * @return array{lines: list<string>, credits: list<array{user_id: int, amount: string, reason: string, at: string, paid_on: string}>}
     */
    private function previewReplay(User $user, float $leftCarry, float $rightCarry, float $binaryPercent): array
    {
        $lines = [];
        $credits = [];
        $packageCap = (float) ($user->package->amount ?? 0);
        $dates = DailyIncomeRun::query()
            ->where('status', DailyIncomeRun::STATUS_COMPLETED)
            ->whereDate('as_of', '>', self::AS_OF)
            ->orderBy('as_of')
            ->get();

        foreach ($dates as $run) {
            $asOf = $run->as_of?->toDateString();
            if ($asOf === null) {
                continue;
            }
            $leftToday = $this->sideVolume($user->id, BinaryTreeLeft::class, $asOf);
            $rightToday = $this->sideVolume($user->id, BinaryTreeRight::class, $asOf);
            $left = $leftToday + $leftCarry;
            $right = $rightToday + $rightCarry;
            $matched = min($left, $right);
            $pay = $this->binaryPay($matched, $binaryPercent, $packageCap);
            $existing = (float) (PaymentDetail::query()
                ->where('user_id', $user->id)
                ->whereDate('paid_on', $asOf)
                ->value('binary_amount') ?? 0);
            $delta = round($pay - $existing, 2);
            $leftCarry = round($left - $matched, 2);
            $rightCarry = round($right - $matched, 2);

            if ($delta > 0) {
                $lines[] = sprintf(
                    '    %s binary $%s (was $%s, +$%s) carry L $%s / R $%s',
                    $asOf,
                    $this->money($pay),
                    $this->money($existing),
                    $this->money($delta),
                    $this->money($leftCarry),
                    $this->money($rightCarry),
                );
            } elseif ($existing > $pay && $matched > 0) {
                $lines[] = sprintf(
                    '    %s keep paid binary $%s (replay $%s, no clawback) carry L $%s / R $%s',
                    $asOf,
                    $this->money($existing),
                    $this->money($pay),
                    $this->money($leftCarry),
                    $this->money($rightCarry),
                );
            } elseif ($matched > 0) {
                $lines[] = sprintf(
                    '    %s binary $%s (already paid) carry L $%s / R $%s',
                    $asOf,
                    $this->money($pay),
                    $this->money($leftCarry),
                    $this->money($rightCarry),
                );
            }
            if ($delta > 0) {
                $credits[] = [
                    'user_id' => $user->id,
                    'amount' => $this->money($delta),
                    'reason' => 'daily_binary',
                    'at' => ($run->created_at ?? $this->cronAt())->toDateTimeString(),
                    'paid_on' => $asOf,
                ];
            }
        }

        return ['lines' => $lines, 'credits' => $credits];
    }

    /**
     * @param  array<int, true>  $walletsTouched
     */
    private function creditAt(User $user, float $amount, string $reason, Carbon $at, array &$walletsTouched): void
    {
        if ($amount <= 0) {
            return;
        }

        $already = WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('reason', $reason)
            ->where('amount', $this->money($amount))
            ->where('created_at', $at)
            ->exists();
        if ($already) {
            return;
        }

        $fresh = $this->wallet->credit($user, $amount, $reason);
        $tx = WalletTransaction::query()->where('user_id', $fresh->id)->latest('id')->first();
        if ($tx) {
            $tx->created_at = $at;
            $tx->updated_at = $at;
            $tx->save();
        }
        $walletsTouched[$fresh->id] = true;
    }

    /**
     * @param  array<int, true>  $walletsTouched
     */
    private function topUpPayment(
        PaymentDetail $existing,
        User $user,
        float $binaryPay,
        float $referralPay,
        float $left,
        float $right,
        Carbon $at,
        array &$walletsTouched,
    ): void {
        $refDelta = round($referralPay - (float) $existing->referral_amount, 2);
        $binDelta = round($binaryPay - (float) $existing->binary_amount, 2);
        if ($refDelta > 0) {
            $existing->referral_amount = $this->money($referralPay);
        }
        if ($binDelta > 0) {
            $existing->binary_amount = $this->money($binaryPay);
            $this->writeBinaryIncome($user->id, self::AS_OF, $binaryPay, $left, $right, $at);
        }
        if ($refDelta > 0 || $binDelta > 0) {
            $existing->total_amount = bcadd(
                bcadd((string) $existing->roi_amount, (string) $existing->binary_amount, 2),
                (string) $existing->referral_amount,
                2
            );
            $existing->save();
        }
        if ($refDelta > 0) {
            $this->creditAt($user, $refDelta, 'daily_referral', $at, $walletsTouched);
        }
        if ($binDelta > 0) {
            $this->creditAt($user, $binDelta, 'daily_binary', $at, $walletsTouched);
        }
    }

    private function writeBinaryIncome(int $userId, string $asOf, float $pay, float $left, float $right, Carbon $at): void
    {
        $bin = BinaryIncome::query()
            ->where('user_id', $userId)
            ->whereDate('earned_on', $asOf)
            ->first();
        if ($bin) {
            $bin->fill([
                'amount' => $this->money($pay),
                'left_volume' => $this->money($left),
                'right_volume' => $this->money($right),
            ]);
            $bin->updated_at = $at;
            $bin->save();

            return;
        }

        $row = new BinaryIncome([
            'user_id' => $userId,
            'amount' => $this->money($pay),
            'left_volume' => $this->money($left),
            'right_volume' => $this->money($right),
            'earned_on' => $asOf,
        ]);
        $row->created_at = $at;
        $row->updated_at = $at;
        $row->save();
    }

    private function rebuildWallet(int $userId): void
    {
        $txs = WalletTransaction::query()
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $credits = '0.00';
        $debits = '0.00';
        foreach ($txs as $tx) {
            if ($tx->type === 'credit') {
                $credits = bcadd($credits, (string) $tx->amount, 2);
            } else {
                $debits = bcadd($debits, (string) $tx->amount, 2);
            }
        }

        $current = (string) User::query()->whereKey($userId)->value('wallet_balance');
        $net = bcsub($credits, $debits, 2);
        $balance = bcsub($current, $net, 2);

        foreach ($txs as $tx) {
            if ($tx->type === 'credit') {
                $balance = bcadd($balance, (string) $tx->amount, 2);
            } else {
                $balance = bcsub($balance, (string) $tx->amount, 2);
            }
            $tx->balance_after = $balance;
            $tx->save();
        }

        User::query()->whereKey($userId)->update(['wallet_balance' => $balance]);
    }

    /**
     * @return list<string>
     */
    private function currentSummary(): array
    {
        $run = DailyIncomeRun::query()->whereDate('as_of', self::AS_OF)->first();
        $rows = PaymentDetail::query()->whereDate('paid_on', self::AS_OF)->count();
        $referral = PaymentDetail::query()->whereDate('paid_on', self::AS_OF)->sum('referral_amount');
        $binary = PaymentDetail::query()->whereDate('paid_on', self::AS_OF)->sum('binary_amount');

        return [
            'Already applied.',
            'daily_income_runs '.self::AS_OF.': '.($run?->status ?? 'missing').' by '.($run?->triggered_by ?? '—').', '.$rows.' ledger rows.',
            '16 Aug referral $'.$this->money((float) $referral).' · binary $'.$this->money((float) $binary).'.',
        ];
    }

    private function sideVolume(int $userId, string $model, string $asOf): float
    {
        return (float) $model::query()
            ->where('user_id', $userId)
            ->whereDate('business_date', $asOf)
            ->sum('amount');
    }

    private function referralVolume(int $userId, string $asOf): float
    {
        return (float) ReferralIncome::query()
            ->where('user_id', $userId)
            ->whereDate('earned_on', $asOf)
            ->sum('amount');
    }

    private function percentOf(float $volume, float $percent): float
    {
        if ($volume <= 0 || $percent <= 0) {
            return 0.0;
        }

        return round($volume * ($percent / 100), 2);
    }

    private function binaryPay(float $matched, float $percent, float $packageCap): float
    {
        $pay = $this->percentOf($matched, $percent);
        if ($packageCap > 0 && $pay > $packageCap) {
            return round($packageCap, 2);
        }

        return $pay;
    }

    private function upsertCarry(int $userId, string $asOf, float $left, float $right, ?Carbon $at = null): void
    {
        $row = CarryForward::query()
            ->where('user_id', $userId)
            ->whereDate('as_of', $asOf)
            ->first();

        if (! $row) {
            $row = new CarryForward([
                'user_id' => $userId,
                'as_of' => $asOf,
            ]);
        }

        $row->left_carry = $this->money($left);
        $row->right_carry = $this->money($right);
        if ($at && ! $row->exists) {
            $row->created_at = $at;
            $row->updated_at = $at;
        }
        $row->save();
    }

    private function money(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
