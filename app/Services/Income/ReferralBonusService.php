<?php

namespace App\Services\Income;

use App\Enums\UserRole;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Services\Wallet\WalletService;

class ReferralBonusService
{
    public function __construct(private readonly WalletService $wallet)
    {
    }

    public function creditForActivation(User $member, ?string $asOf = null): void
    {
        $member->loadMissing('package');
        $percent = (float) config('citymax.income.referral_percent', 10);
        $packageAmount = (float) ($member->package->amount ?? 0);
        if ($percent <= 0 || $packageAmount <= 0 || ! $member->sponsor_id) {
            return;
        }

        $sponsor = User::query()
            ->whereKey($member->sponsor_id)
            ->where('role', UserRole::Customer)
            ->first();

        if (! $sponsor || $sponsor->id === $member->id) {
            return;
        }

        $asOf = $asOf ?: now()->toDateString();

        $alreadyPaid = ReferralIncome::query()
            ->where('user_id', $sponsor->id)
            ->where('from_user_id', $member->id)
            ->exists();

        if ($alreadyPaid) {
            return;
        }

        $amount = number_format(round($packageAmount * ($percent / 100), 2), 2, '.', '');
        if (bccomp($amount, '0.00', 2) <= 0) {
            return;
        }

        ReferralIncome::query()->create([
            'user_id' => $sponsor->id,
            'from_user_id' => $member->id,
            'amount' => $amount,
            'earned_on' => $asOf,
        ]);

        $this->wallet->credit($sponsor, $amount, 'referral_bonus');
    }
}
