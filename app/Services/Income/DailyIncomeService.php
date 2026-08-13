<?php

namespace App\Services\Income;

use App\Enums\UserRole;
use App\Models\BinaryIncome;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\CarryForward;
use App\Models\PaymentDetail;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Services\Calc\CalcDispatcher;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

class DailyIncomeService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly CalcDispatcher $calc,
    ) {
    }

    /**
     * Laravel owns money: ROI + binary matching. Node is notified but must not credit wallets.
     *
     * @return array{processed: int, total: string, asOf: string}
     */
    public function run(?string $asOf = null): array
    {
        $asOf = $asOf ?: now()->toDateString();
        $this->calc->dailyIncome(['asOf' => $asOf, 'source' => 'laravel']);

        $processed = 0;
        $totalPaid = '0.00';
        $binaryPercent = (float) config('citymax.income.binary_percent', 10);

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
            ->chunkById(200, function ($users) use ($asOf, $binaryPercent, &$processed, &$totalPaid) {
                foreach ($users as $user) {
                    $paid = $this->payUserForDay($user, $asOf, $binaryPercent);
                    if ($paid !== null) {
                        $processed++;
                        $totalPaid = bcadd($totalPaid, $paid, 2);
                    }
                    $user->unsetRelation('package');
                }
            });

        return [
            'processed' => $processed,
            'total' => $totalPaid,
            'asOf' => $asOf,
        ];
    }

    private function payUserForDay(User $user, string $asOf, float $binaryPercent): ?string
    {
        $packageAmount = (float) ($user->package->amount ?? 0);
        $roiPercent = (float) ($user->package->roi_percent ?? 0);
        $roi = $packageAmount > 0 && $roiPercent > 0
            ? round($packageAmount * ($roiPercent / 100), 2)
            : 0.0;

        $binary = $this->matchBinary($user, $asOf, $binaryPercent);

        $referral = (float) ReferralIncome::query()
            ->where('user_id', $user->id)
            ->whereDate('earned_on', $asOf)
            ->sum('amount');

        if ($roi <= 0 && $binary['pay'] <= 0 && $referral <= 0) {
            if ($binary['left'] > 0 || $binary['right'] > 0) {
                $this->storeCarry($user->id, $asOf, $binary['left_carry'], $binary['right_carry']);
            }

            return null;
        }

        $roiFormatted = number_format($roi, 2, '.', '');
        $binaryFormatted = number_format($binary['pay'], 2, '.', '');
        $referralFormatted = number_format($referral, 2, '.', '');
        $walletCredit = bcadd($roiFormatted, $binaryFormatted, 2);
        $totalDisplay = bcadd($walletCredit, $referralFormatted, 2);

        DB::transaction(function () use ($user, $asOf, $roiFormatted, $binaryFormatted, $referralFormatted, $totalDisplay, $roi, $binary) {
            PaymentDetail::query()->create([
                'user_id' => $user->id,
                'roi_amount' => $roiFormatted,
                'binary_amount' => $binaryFormatted,
                'referral_amount' => $referralFormatted,
                'total_amount' => $totalDisplay,
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

            $this->storeCarry($user->id, $asOf, $binary['left_carry'], $binary['right_carry']);
        });

        return $walletCredit;
    }

    /**
     * @return array{pay: float, left: float, right: float, left_carry: float, right_carry: float}
     */
    private function matchBinary(User $user, string $asOf, float $binaryPercent): array
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
