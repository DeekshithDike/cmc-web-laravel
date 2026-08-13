<?php

namespace App\Services\Income;

use App\Enums\UserRole;
use App\Models\ReferralIncome;
use App\Models\User;

class ReferralBonusService
{
    /**
     * Store the new member's full package amount for the sponsor.
     * Same as the reference Node `updateReferralIncom` / `insertReferralIncom`:
     * wallet is not credited here. DailyIncomeService pays INCOME_REFERRAL_PERCENT
     * of that day's stored package total.
     */
    public function recordForActivation(User $member, ?string $asOf = null): void
    {
        $member->loadMissing('package');
        $packageAmount = (float) ($member->package->amount ?? 0);
        if ($packageAmount <= 0 || ! $member->sponsor_id) {
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

        $alreadyRecorded = ReferralIncome::query()
            ->where('from_user_id', $member->id)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        ReferralIncome::query()->create([
            'user_id' => $sponsor->id,
            'from_user_id' => $member->id,
            'amount' => number_format($packageAmount, 2, '.', ''),
            'earned_on' => $asOf,
        ]);
    }
}
