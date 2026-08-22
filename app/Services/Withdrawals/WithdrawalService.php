<?php

namespace App\Services\Withdrawals;

use App\Enums\PaymentProvider;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\UsdtWalletAddress;
use App\Services\Payouts\NowPaymentsPayoutGateway;
use App\Services\Payouts\PayoutGatewayManager;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class WithdrawalService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly PayoutGatewayManager $payouts,
    ) {
    }

    public function request(User $user, float $amount, string $walletAddress): Withdrawal
    {
        $minimum = (float) config('citymax.withdrawal.minimum');
        $fee = (float) config('citymax.withdrawal.fee');

        if ($amount < $minimum) {
            throw new InvalidArgumentException("Minimum withdrawal is \${$minimum}.");
        }

        $walletAddress = trim($walletAddress);
        $network = UsdtWalletAddress::network($walletAddress);
        if ($network === null) {
            throw new InvalidArgumentException('Wallet address must be USDT TRC-20 (starts with T) or BEP-20 (0x followed by 40 hex characters).');
        }

        $payoutCurrency = UsdtWalletAddress::nowPaymentsCurrency($walletAddress);

        return DB::transaction(function () use ($user, $amount, $walletAddress, $fee, $network, $payoutCurrency) {
            $this->wallet->debit($user, $amount, 'withdrawal_request');

            return Withdrawal::query()->create([
                'user_id' => $user->id,
                'amount' => number_format($amount, 2, '.', ''),
                'fee' => number_format($fee, 2, '.', ''),
                'payable_amount' => number_format(max($amount - $fee, 0), 2, '.', ''),
                'wallet_address' => $walletAddress,
                'status' => WithdrawalStatus::Pending,
                'meta' => [
                    'network' => $network,
                    'payout_currency' => $payoutCurrency,
                ],
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

            $refund = max((float) $locked->amount, 0);
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
                $refund = max((float) $confirmed->amount, 0);
                $this->wallet->credit($confirmed->user, $refund, 'withdrawal_payout_failed');
            }

            return $confirmed;
        });
    }

    /**
     * Backup for missed payout IPNs. Reuses applyPayoutWebhook so webhook + sync cannot double-pay or double-refund.
     *
     * @return array{checked: int, completed: int, declined: int, unchanged: int, skipped: int, errors: list<string>}
     */
    public function syncProcessingPayouts(): array
    {
        $gateway = $this->payouts->driver(PaymentProvider::NowPayments);
        if (! $gateway instanceof NowPaymentsPayoutGateway) {
            throw new InvalidArgumentException('NOWPayments payout driver is unavailable.');
        }

        $summary = [
            'checked' => 0,
            'completed' => 0,
            'declined' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $rows = Withdrawal::query()
            ->where('status', WithdrawalStatus::Processing)
            ->orderBy('id')
            ->get();

        foreach ($rows as $withdrawal) {
            $summary['checked']++;
            $provider = (string) ($withdrawal->payout_provider ?? '');
            if ($provider !== PaymentProvider::NowPayments->value || ! filled($withdrawal->payout_ref)) {
                $summary['skipped']++;
                continue;
            }

            try {
                $payload = $gateway->fetchPayoutStatus($withdrawal);
                $status = $gateway->mapPayloadStatus($payload, (string) $withdrawal->payout_ref);
                if ($status === WithdrawalStatus::Processing->value) {
                    $summary['unchanged']++;
                    continue;
                }

                $updated = $this->applyPayoutWebhook($withdrawal, $status, [
                    'provider_ref' => $withdrawal->payout_ref,
                    'payout_status_sync' => $payload,
                ]);

                if ($updated->status === WithdrawalStatus::Completed) {
                    $summary['completed']++;
                } elseif ($updated->status === WithdrawalStatus::Declined) {
                    $summary['declined']++;
                } else {
                    $summary['unchanged']++;
                }
            } catch (Throwable $e) {
                $summary['errors'][] = 'Withdrawal #'.$withdrawal->id.': '.$e->getMessage();
                Log::warning('Payout status sync failed', [
                    'withdrawal_id' => $withdrawal->id,
                    'payout_ref' => $withdrawal->payout_ref,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    private function isPending(Withdrawal $withdrawal): bool
    {
        $status = $withdrawal->status;

        return $status instanceof WithdrawalStatus
            ? $status === WithdrawalStatus::Pending
            : (string) $status === WithdrawalStatus::Pending->value;
    }
}
