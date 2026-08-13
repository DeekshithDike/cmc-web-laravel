<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Enums\UserStatus;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Business\BusinessVolumeService;
use App\Services\Calc\CalcDispatcher;
use App\Services\Income\ReferralBonusService;
use App\Services\Payments\NowPayments\NowPaymentsClient;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly CalcDispatcher $calc,
        private readonly BusinessVolumeService $volumes,
        private readonly NowPaymentsClient $nowPayments,
        private readonly ReferralBonusService $referrals,
    ) {
    }

    /**
     * True when invite registration should wait for payment before login access.
     * Stub mode (local/testing without keys) keeps the immediate-active flow.
     */
    public function requiresLiveCheckout(): bool
    {
        $provider = (string) config('payments.default_receive', 'manual');

        if ($provider !== PaymentProvider::NowPayments->value) {
            return false;
        }

        return $this->nowPayments->configured() || ! PaymentEnvironment::allowsStub();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{transaction: PaymentTransaction, redirect_url?: string|null, message?: string}
     */
    public function start(User $user, float $amount, ?PaymentProvider $provider = null, array $meta = []): array
    {
        return $this->gateways->driver($provider)->initiate($user, $amount, $meta);
    }

    public function confirm(PaymentTransaction $transaction, array $payload = []): PaymentTransaction
    {
        $incomingStatus = (string) ($payload['status'] ?? 'completed');
        $placeMemberPayload = null;

        $confirmed = DB::transaction(function () use ($transaction, $payload, $incomingStatus, &$placeMemberPayload) {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            // Idempotent: ignore duplicate IPNs / double admin confirms on terminal rows.
            if (in_array($locked->status, ['completed', 'failed'], true)) {
                if ($locked->status === 'completed' || $incomingStatus === $locked->status) {
                    return $locked;
                }
            }

            if ($incomingStatus === 'completed' && ! $this->paidAmountCovers($locked, $payload)) {
                $incomingStatus = 'failed';
                $payload['amount_mismatch'] = true;
            }

            $confirmed = $this->gateways
                ->driver($locked->provider)
                ->confirm($locked, array_merge($payload, ['status' => $incomingStatus]));

            if ($confirmed->status === 'completed' && $confirmed->user_id) {
                $this->finalizeMemberAfterPayment($confirmed);
                $placeMemberPayload = [
                    'userId' => $confirmed->user_id,
                    'packageId' => $confirmed->package_id,
                    'source' => 'payment_confirm',
                    'provider' => $confirmed->provider?->value,
                    'providerRef' => $confirmed->provider_ref,
                ];
            }

            return $confirmed;
        });

        if ($placeMemberPayload) {
            $this->calc->placeMember($placeMemberPayload);
        }

        return $confirmed;
    }

    private function finalizeMemberAfterPayment(PaymentTransaction $transaction): void
    {
        $user = User::query()->whereKey($transaction->user_id)->lockForUpdate()->first();
        if (! $user) {
            return;
        }

        $wasUnpaid = ! $user->payment_status;

        if ($wasUnpaid) {
            $user->payment_status = true;
            $user->is_active = true;
            $user->status = UserStatus::Active;
            if (! $user->expiry_date) {
                $user->expiry_date = now()->addWeekdays((int) config('citymax.membership.weekdays', 150))->toDateString();
            }
            $user->save();

            $amount = (float) $transaction->amount;
            if ($amount > 0) {
                $this->volumes->recordPlacementVolume($user, $amount);
            }

            $user->loadMissing('package');
            $this->referrals->creditForActivation($user);
        }
    }

    /**
     * Reject completed IPNs that pay less than the stored package amount.
     */
    private function paidAmountCovers(PaymentTransaction $transaction, array $payload): bool
    {
        $webhook = is_array($payload['webhook'] ?? null) ? $payload['webhook'] : $payload;
        $paid = $webhook['price_amount'] ?? $webhook['actually_paid_at_fiat'] ?? null;

        if ($paid === null || $paid === '') {
            return PaymentEnvironment::allowsStub();
        }

        if (! is_numeric($paid)) {
            return false;
        }

        return bccomp(number_format((float) $paid, 2, '.', ''), number_format((float) $transaction->amount, 2, '.', ''), 2) >= 0;
    }
}
