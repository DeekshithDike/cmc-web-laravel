<?php

namespace App\Services\Withdrawals;

use App\Enums\PaymentProvider;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\UsdtWalletAddress;
use App\Services\Payouts\PayoutGatewayManager;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WithdrawalService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly PayoutGatewayManager $payouts,
    ) {
    }

    public function request(User $user, float $amount, string $walletAddress): Withdrawal
    {
        $minimum = (float) config('citymax.withdrawal.minimum', 20);
        $fee = (float) config('citymax.withdrawal.fee', 5);

        if ($amount < $minimum) {
            throw new InvalidArgumentException("Minimum withdrawal is \${$minimum}.");
        }

        $walletAddress = trim($walletAddress);
        if (! UsdtWalletAddress::isEvm($walletAddress)) {
            throw new InvalidArgumentException('Wallet address must be a USDT ERC-20 or BEP-20 address (0x followed by 40 hex characters).');
        }

        return DB::transaction(function () use ($user, $amount, $walletAddress, $fee) {
            $this->wallet->debit($user, $amount, 'withdrawal_request');

            return Withdrawal::query()->create([
                'user_id' => $user->id,
                'amount' => number_format($amount, 2, '.', ''),
                'fee' => number_format($fee, 2, '.', ''),
                'payable_amount' => number_format(max($amount - $fee, 0), 2, '.', ''),
                'wallet_address' => $walletAddress,
                'status' => WithdrawalStatus::Pending,
            ]);
        });
    }

    /**
     * Admin "Pay Now" — routes through the configured payout driver (manual / NOWPayments / future).
     */
    public function complete(Withdrawal $withdrawal, ?string $remarks = null, ?PaymentProvider $provider = null): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $remarks, $provider) {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if (! $this->isPending($locked)) {
                throw new InvalidArgumentException('Only pending withdrawals can be paid.');
            }

            $result = $this->payouts->driver($provider)->send($locked, [
                'remarks' => $remarks,
            ]);

            return $result['withdrawal'];
        });
    }

    public function decline(Withdrawal $withdrawal, ?string $remarks = null): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $remarks) {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== WithdrawalStatus::Pending
                && (string) $locked->status !== 'pending') {
                throw new InvalidArgumentException('Only pending withdrawals can be declined.');
            }

            $refund = max((float) $locked->amount - (float) $locked->fee, 0);
            $this->wallet->credit($locked->user, $refund, 'withdrawal_declined');

            $locked->status = WithdrawalStatus::Declined;
            $locked->remarks = $remarks;
            $locked->processed_at = now();
            $locked->save();

            return $locked;
        });
    }

    public function applyPayoutWebhook(Withdrawal $withdrawal, string $status, array $payload = []): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $status, $payload) {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === WithdrawalStatus::Completed || $locked->status === WithdrawalStatus::Declined) {
                return $locked;
            }

            $provider = PaymentProvider::tryFrom((string) ($locked->payout_provider ?? ''))
                ?? PaymentProvider::from((string) config('payments.default_payout', 'manual'));

            $confirmed = $this->payouts->driver($provider)->confirm($locked, array_merge($payload, [
                'status' => $status,
            ]));

            if ($confirmed->status === WithdrawalStatus::Declined) {
                $refund = max((float) $confirmed->amount - (float) $confirmed->fee, 0);
                $this->wallet->credit($confirmed->user, $refund, 'withdrawal_payout_failed');
            }

            return $confirmed;
        });
    }

    private function isPending(Withdrawal $withdrawal): bool
    {
        $status = $withdrawal->status;

        return $status instanceof WithdrawalStatus
            ? $status === WithdrawalStatus::Pending
            : (string) $status === WithdrawalStatus::Pending->value;
    }
}
