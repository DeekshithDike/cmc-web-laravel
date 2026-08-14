<?php

namespace App\Services\Payments;

use App\Enums\PaymentProvider;
use App\Enums\UserStatus;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Auth\MemberCredentialsNotifier;
use App\Services\Auth\OneTimeCredentials;
use App\Services\Business\BusinessVolumeService;
use App\Services\Calc\CalcDispatcher;
use App\Services\Income\ReferralBonusService;
use App\Services\Membership\MembershipService;
use App\Services\Payments\NowPayments\NowPaymentsClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly CalcDispatcher $calc,
        private readonly BusinessVolumeService $volumes,
        private readonly NowPaymentsClient $nowPayments,
        private readonly ReferralBonusService $referrals,
        private readonly MembershipService $membership,
        private readonly OneTimeCredentials $credentials,
        private readonly MemberCredentialsNotifier $credentialsMailer,
    ) {
    }

    /**
     * True when invite registration should redirect to hosted checkout.
     * Stub mode (local/testing without keys) keeps a pending invoice without a live URL.
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
    public function start(?User $user, float $amount, ?PaymentProvider $provider = null, array $meta = []): array
    {
        return $this->gateways->driver($provider)->initiate($user, $amount, $meta);
    }

    public function confirm(PaymentTransaction $transaction, array $payload = []): PaymentTransaction
    {
        $incomingStatus = (string) ($payload['status'] ?? 'completed');
        $placeMemberPayload = null;
        $credentialsUser = null;

        $confirmed = DB::transaction(function () use ($transaction, $payload, $incomingStatus, &$placeMemberPayload, &$credentialsUser) {
            /** @var PaymentTransaction $locked */
            $locked = PaymentTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $signup = $this->pendingSignup($locked);
            $powerActivation = $this->pendingPowerActivation($locked);

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

            if ($confirmed->status !== 'completed') {
                return $confirmed;
            }

            if ($confirmed->user_id) {
                $this->finalizeMemberAfterPayment($confirmed);
                $placeMemberPayload = [
                    'userId' => $confirmed->user_id,
                    'packageId' => $confirmed->package_id,
                    'source' => 'payment_confirm',
                    'provider' => $confirmed->provider?->value,
                    'providerRef' => $confirmed->provider_ref,
                ];

                return $confirmed;
            }

            if ($powerActivation !== null) {
                try {
                    $user = $this->membership->activatePowerId((int) $powerActivation['power_id'], $powerActivation, false);
                    $this->attachCredentials($confirmed, $user);
                    $credentialsUser = $user;
                    $placeMemberPayload = $this->membership->placeMemberPayload($user, 'DUMMY_ACTIVATED');
                } catch (Throwable $e) {
                    Log::error('Power ID payment confirmed but member was not activated', [
                        'transaction_id' => $confirmed->id,
                        'error' => $e->getMessage(),
                    ]);
                    $confirmed->meta = array_merge($confirmed->meta ?? [], [
                        'activation_error' => $e->getMessage(),
                    ]);
                    $confirmed->save();
                }

                return $confirmed->fresh();
            }

            if ($signup === null) {
                return $confirmed;
            }

            try {
                $user = $this->membership->createActiveMember($signup, false);
                $this->attachCredentials($confirmed, $user);
                $credentialsUser = $user;
                $placeMemberPayload = $this->membership->placeMemberPayload($user, 'NORMAL');
            } catch (Throwable $e) {
                Log::error('Invite payment confirmed but member was not created', [
                    'transaction_id' => $confirmed->id,
                    'error' => $e->getMessage(),
                ]);
                $confirmed->meta = array_merge($confirmed->meta ?? [], [
                    'activation_error' => $e->getMessage(),
                ]);
                $confirmed->save();
            }

            return $confirmed->fresh();
        });

        if ($placeMemberPayload) {
            $this->calc->placeMember($placeMemberPayload);
        }

        if ($credentialsUser) {
            $this->credentialsMailer->email($credentialsUser);
        }

        return $confirmed;
    }

    private function attachCredentials(PaymentTransaction $confirmed, User $user): void
    {
        $token = $this->credentials->store(
            (int) $user->id,
            (string) $user->plain_password,
            route('customer.login')
        );

        $confirmed->user_id = $user->id;
        $confirmed->meta = array_merge($confirmed->meta ?? [], [
            'credentials_token' => $token,
            'login_id' => $user->id,
        ]);
        $confirmed->save();
    }

    /**
     * @return array{power_id:int,name:string,email:string,phone:?string,country:?string,package_id:int}|null
     */
    private function pendingPowerActivation(PaymentTransaction $transaction): ?array
    {
        $meta = $transaction->meta['power_activation'] ?? null;
        if (! is_array($meta)) {
            return null;
        }

        $required = ['power_id', 'name', 'email', 'package_id'];
        foreach ($required as $key) {
            if (! isset($meta[$key]) || $meta[$key] === '' || $meta[$key] === null) {
                return null;
            }
        }

        return [
            'power_id' => (int) $meta['power_id'],
            'name' => (string) $meta['name'],
            'email' => (string) $meta['email'],
            'phone' => isset($meta['phone']) ? (string) $meta['phone'] : null,
            'country' => isset($meta['country']) ? (string) $meta['country'] : null,
            'package_id' => (int) $meta['package_id'],
        ];
    }

    /**
     * @return array{name:string,email:string,phone:?string,country:?string,package_id:int,parent_id:int,position:string,sponsor_id:int}|null
     */
    private function pendingSignup(PaymentTransaction $transaction): ?array
    {
        $signup = $transaction->meta['signup'] ?? null;
        if (! is_array($signup)) {
            return null;
        }

        $required = ['name', 'email', 'package_id', 'parent_id', 'position', 'sponsor_id'];
        foreach ($required as $key) {
            if (! isset($signup[$key]) || $signup[$key] === '' || $signup[$key] === null) {
                return null;
            }
        }

        if (! in_array((string) $signup['position'], ['left', 'right'], true)) {
            return null;
        }

        return [
            'name' => (string) $signup['name'],
            'email' => (string) $signup['email'],
            'phone' => isset($signup['phone']) ? (string) $signup['phone'] : null,
            'country' => isset($signup['country']) ? (string) $signup['country'] : null,
            'package_id' => (int) $signup['package_id'],
            'parent_id' => (int) $signup['parent_id'],
            'position' => (string) $signup['position'],
            'sponsor_id' => (int) $signup['sponsor_id'],
        ];
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
                $user->expiry_date = now()->addWeekdays((int) config('citymax.membership.weekdays'))->toDateString();
            }
            $user->save();

            $amount = (float) $transaction->amount;
            if ($amount > 0) {
                $this->volumes->recordPlacementVolume($user, $amount);
            }

            $user->loadMissing('package');
            $this->referrals->recordForActivation($user);
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
