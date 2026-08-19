<?php

namespace App\Services\Verification;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\User;

final class CustomerVerificationReport
{
    /**
     * @param  array{active: bool, paid: bool, expired: bool, eligible: bool}  $eligibility
     * @param  array{left: float, right: float, matched: float, roi: float, binary: float, referral: float, match_days: int}  $totals
     * @param  array{tone: string, text: string}  $answer
     * @param  array{left: string, right: string}  $carryNow
     */
    public function __construct(
        public readonly User $user,
        public readonly string $today,
        public readonly string $from,
        public readonly string $to,
        public readonly array $eligibility,
        public readonly array $totals,
        public readonly array $answer,
        public readonly array $carryNow,
        public readonly bool $yesterdayCompleted,
        public readonly LengthAwarePaginator $ledger,
        public readonly ?LengthAwarePaginator $sources,
        public readonly ?LengthAwarePaginator $referrals,
    ) {
    }
}
