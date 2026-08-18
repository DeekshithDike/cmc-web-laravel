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
use App\Services\Calc\CalcDispatcher;
use App\Services\Wallet\WalletService;
use App\Support\IncomeCalendar;
use Illuminate\Support\Facades\DB;

class DailyIncomeService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly CalcDispatcher $calc,
    ) {
    }

    public function previousDate(?\Illuminate\Support\Carbon $now = null): string
    {
        return IncomeCalendar::previousDate($now);
    }

    /**
     * Pay ROI, binary matching, and referral for one calendar day (00:00–23:59).
     * Cron and admin both default to yesterday. A completed run is never repeated.
     *
     * @return array{processed: int, total: string, asOf: string, skipped: bool, message: string}
     */
    public function run(?string $asOf = null, string $triggeredBy = 'command'): array
    {
        $asOf = $asOf ?: $this->previousDate();
        $claim = $this->claimRun($asOf, $triggeredBy);

        if ($claim['skipped']) {
            return $claim['result'];
        }

        /** @var DailyIncomeRun $run */
        $run = $claim['run'];

        $this->calc->dailyIncome(['asOf' => $asOf, 'source' => 'laravel', 'triggeredBy' => $triggeredBy]);

        $processed = 0;
        $totalPaid = '0.00';
        $binaryPercent = (float) config('citymax.income.binary_percent');
        $referralPercent = (float) config('citymax.income.referral_percent');

        User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->where('payment_status', true)
            ->whereDate('expiry_date', '>=', $asOf)
            ->whereNotExists(function ($query) use ($asOf) {
                $query->selectRaw('1')
                    ->from('payment_details')
                    ->whereColumn('payment_details.user_id', 'users.id')
                    ->whereDate('payment_details.paid_on', $asOf);
            })
            ->with('package:id,amount,roi_percent')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($asOf, $binaryPercent, $referralPercent, &$processed, &$totalPaid) {
                foreach ($users as $user) {
                    $paid = $this->payUserForDay($user, $asOf, $binaryPercent, $referralPercent);
                    if ($paid !== null) {
                        $processed++;
                        $totalPaid = bcadd($totalPaid, $paid, 2);
                    }
                    $user->unsetRelation('package');
                }
            });

        $run->update([
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => $triggeredBy,
            'processed' => $processed,
            'total_paid' => $totalPaid,
        ]);

        return [
            'processed' => $processed,
            'total' => $totalPaid,
            'asOf' => $asOf,
            'skipped' => false,
            'message' => "Daily income for {$asOf}: {$processed} members, \${$totalPaid} paid.",
        ];
    }

    /**
     * Pay 1% ROI for a past day without re-running binary or referral.
     * Members who already have roi_amount > 0 for that day are skipped.
     *
     * @return array{credited: int, total: string, asOf: string, message: string}
     */
    public function creditMissingRoi(string $asOf, string $triggeredBy = 'admin'): array
    {
        if (! IncomeCalendar::paysRoiOn($asOf)) {
            return [
                'credited' => 0,
                'total' => '0.00',
                'asOf' => $asOf,
                'message' => "ROI is not paid on {$asOf} (Saturday/Sunday).",
            ];
        }

        $credited = 0;
        $totalRoi = '0.00';

        User::query()
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->where('payment_status', true)
            ->whereDate('expiry_date', '>=', $asOf)
            ->with('package:id,amount,roi_percent')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($asOf, &$credited, &$totalRoi) {
                foreach ($users as $user) {
                    $packageAmount = (float) ($user->package->amount ?? 0);
                    $roiPercent = (float) ($user->package->roi_percent ?? 0);
                    $roi = $packageAmount > 0 && $roiPercent > 0
                        ? round($packageAmount * ($roiPercent / 100), 2)
                        : 0.0;

                    if ($roi <= 0) {
                        continue;
                    }

                    $roiFormatted = number_format($roi, 2, '.', '');
                    $existing = PaymentDetail::query()
                        ->where('user_id', $user->id)
                        ->whereDate('paid_on', $asOf)
                        ->first();

                    if ($existing && (float) $existing->roi_amount > 0) {
                        continue;
                    }

                    DB::transaction(function () use ($user, $asOf, $roi, $roiFormatted, $existing) {
                        if ($existing) {
                            $existing->roi_amount = $roiFormatted;
                            $existing->total_amount = bcadd(
                                bcadd((string) $existing->binary_amount, (string) $existing->referral_amount, 2),
                                $roiFormatted,
                                2
                            );
                            $existing->save();
                        } else {
                            PaymentDetail::query()->create([
                                'user_id' => $user->id,
                                'roi_amount' => $roiFormatted,
                                'binary_amount' => '0.00',
                                'referral_amount' => '0.00',
                                'total_amount' => $roiFormatted,
                                'paid_on' => $asOf,
                            ]);
                        }

                        $this->wallet->credit($user, $roi, 'daily_roi');
                    });

                    $credited++;
                    $totalRoi = bcadd($totalRoi, $roiFormatted, 2);
                    $user->unsetRelation('package');
                }
            });

        $run = DailyIncomeRun::query()->whereDate('as_of', $asOf)->first();
        if ($run) {
            $run->update([
                'triggered_by' => $triggeredBy,
                'processed' => PaymentDetail::query()->whereDate('paid_on', $asOf)->count(),
                'total_paid' => number_format(
                    (float) PaymentDetail::query()->whereDate('paid_on', $asOf)->sum('total_amount'),
                    2,
                    '.',
                    ''
                ),
            ]);
        }

        return [
            'credited' => $credited,
            'total' => $totalRoi,
            'asOf' => $asOf,
            'message' => "Missing ROI for {$asOf}: {$credited} members, \${$totalRoi} credited. Referral and binary were not re-paid.",
        ];
    }

    /**
     * @return array{skipped: bool, run?: DailyIncomeRun, result?: array{processed: int, total: string, asOf: string, skipped: bool, message: string}}
     */
    private function claimRun(string $asOf, string $triggeredBy): array
    {
        return DB::transaction(function () use ($asOf, $triggeredBy) {
            $run = DailyIncomeRun::query()->whereDate('as_of', $asOf)->lockForUpdate()->first();

            if ($run && $run->status === DailyIncomeRun::STATUS_COMPLETED) {
                $total = number_format((float) $run->total_paid, 2, '.', '');

                return [
                    'skipped' => true,
                    'result' => [
                        'processed' => 0,
                        'total' => $total,
                        'asOf' => $asOf,
                        'skipped' => true,
                        'message' => "Daily income for {$asOf} was already calculated ({$run->processed} members, \${$total}).",
                    ],
                ];
            }

            if (! $run) {
                $run = DailyIncomeRun::query()->create([
                    'as_of' => $asOf,
                    'status' => DailyIncomeRun::STATUS_RUNNING,
                    'triggered_by' => $triggeredBy,
                    'processed' => 0,
                    'total_paid' => '0.00',
                ]);
            }

            return ['skipped' => false, 'run' => $run];
        });
    }

    private function payUserForDay(User $user, string $asOf, float $binaryPercent, float $referralPercent): ?string
    {
        $packageAmount = (float) ($user->package->amount ?? 0);
        $roiPercent = (float) ($user->package->roi_percent ?? 0);
        $roi = IncomeCalendar::paysRoiOn($asOf) && $packageAmount > 0 && $roiPercent > 0
            ? round($packageAmount * ($roiPercent / 100), 2)
            : 0.0;

        $binary = $this->matchBinary($user, $asOf, $binaryPercent, $packageAmount);

        $referralVolume = (float) ReferralIncome::query()
            ->where('user_id', $user->id)
            ->whereDate('earned_on', $asOf)
            ->sum('amount');
        $referralPay = $referralVolume > 0 && $referralPercent > 0
            ? round($referralVolume * ($referralPercent / 100), 2)
            : 0.0;

        if ($roi <= 0 && $binary['pay'] <= 0 && $referralPay <= 0) {
            if ($binary['left'] > 0 || $binary['right'] > 0) {
                $this->storeCarry($user->id, $asOf, $binary['left_carry'], $binary['right_carry']);
            }

            return null;
        }

        $roiFormatted = number_format($roi, 2, '.', '');
        $binaryFormatted = number_format($binary['pay'], 2, '.', '');
        $referralFormatted = number_format($referralPay, 2, '.', '');
        $walletCredit = bcadd(bcadd($roiFormatted, $binaryFormatted, 2), $referralFormatted, 2);

        DB::transaction(function () use ($user, $asOf, $roiFormatted, $binaryFormatted, $referralFormatted, $walletCredit, $roi, $binary, $referralPay) {
            PaymentDetail::query()->create([
                'user_id' => $user->id,
                'roi_amount' => $roiFormatted,
                'binary_amount' => $binaryFormatted,
                'referral_amount' => $referralFormatted,
                'total_amount' => $walletCredit,
                'paid_on' => $asOf,
            ]);

            if ($roi > 0) {
                $this->wallet->credit($user, $roi, 'daily_roi');
            }

            if ($binary['pay'] > 0) {
                BinaryIncome::query()->create([
                    'user_id' => $user->id,
                    'amount' => $binaryFormatted,
                    'left_volume' => number_format($binary['left'], 2, '.', ''),
                    'right_volume' => number_format($binary['right'], 2, '.', ''),
                    'earned_on' => $asOf,
                ]);
                $this->wallet->credit($user, $binary['pay'], 'daily_binary');
            }

            if ($referralPay > 0) {
                $this->wallet->credit($user, $referralPay, 'daily_referral');
            }

            $this->storeCarry($user->id, $asOf, $binary['left_carry'], $binary['right_carry']);
        });

        return $walletCredit;
    }

    /**
     * @return array{pay: float, left: float, right: float, left_carry: float, right_carry: float}
     */
    private function matchBinary(User $user, string $asOf, float $binaryPercent, float $packageCap): array
    {
        $leftToday = (float) BinaryTreeLeft::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $asOf)
            ->sum('amount');
        $rightToday = (float) BinaryTreeRight::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $asOf)
            ->sum('amount');

        $carry = CarryForward::query()
            ->where('user_id', $user->id)
            ->whereDate('as_of', '<', $asOf)
            ->orderByDesc('as_of')
            ->first();

        $left = $leftToday + (float) ($carry->left_carry ?? 0);
        $right = $rightToday + (float) ($carry->right_carry ?? 0);
        $matched = min($left, $right);
        $pay = $matched > 0 && $binaryPercent > 0
            ? round($matched * ($binaryPercent / 100), 2)
            : 0.0;

        if ($packageCap > 0 && $pay > $packageCap) {
            $pay = round($packageCap, 2);
        }

        return [
            'pay' => $pay,
            'left' => $left,
            'right' => $right,
            'left_carry' => round($left - $matched, 2),
            'right_carry' => round($right - $matched, 2),
        ];
    }

    private function storeCarry(int $userId, string $asOf, float $left, float $right): void
    {
        CarryForward::query()->updateOrCreate(
            ['user_id' => $userId, 'as_of' => $asOf],
            [
                'left_carry' => number_format($left, 2, '.', ''),
                'right_carry' => number_format($right, 2, '.', ''),
            ]
        );
    }
}
