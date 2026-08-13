<?php

namespace App\Services\Payouts;

use App\Contracts\PayoutGatewayInterface;
use App\Enums\PaymentProvider;
use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

/**
 * Manual payout — admin marks paid after sending crypto outside the app.
 */
class ManualPayoutGateway implements PayoutGatewayInterface
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Manual;
    }

    public function send(Withdrawal $withdrawal, array $meta = []): array
    {
        $withdrawal->payout_provider = PaymentProvider::Manual->value;
        $withdrawal->payout_ref = $withdrawal->payout_ref ?: 'MAN-PAYOUT-'.uniqid();
        $withdrawal->status = WithdrawalStatus::Completed;
        $withdrawal->remarks = $meta['remarks'] ?? $withdrawal->remarks;
        $withdrawal->processed_at = now();
        $withdrawal->meta = array_merge($withdrawal->meta ?? [], $meta, ['manual' => true]);
        $withdrawal->save();

        return [
            'withdrawal' => $withdrawal->fresh(),
            'provider_ref' => $withdrawal->payout_ref,
            'message' => 'Withdrawal marked completed (manual payout).',
        ];
    }

    public function confirm(Withdrawal $withdrawal, array $payload = []): Withdrawal
    {
        $withdrawal->status = WithdrawalStatus::tryFrom((string) ($payload['status'] ?? 'completed'))
            ?? WithdrawalStatus::Completed;
        $withdrawal->meta = array_merge($withdrawal->meta ?? [], $payload);
        $withdrawal->processed_at = $withdrawal->processed_at ?? now();
        $withdrawal->save();

        return $withdrawal->fresh();
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function findWebhookWithdrawal(Request $request): ?Withdrawal
    {
        return null;
    }

    public function webhookStatus(Request $request): string
    {
        return WithdrawalStatus::Pending->value;
    }
}
