<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\PostgresIdSequences;
use Illuminate\Http\Request;

class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Manual;
    }

    public function initiate(?User $user, float $amount, array $meta = []): array
    {
        $transaction = PostgresIdSequences::run(fn () => PaymentTransaction::query()->create([
            'user_id' => $user?->id,
            'package_id' => $meta['package_id'] ?? $user?->package_id,
            'provider' => PaymentProvider::Manual,
            'provider_ref' => 'MAN-'.uniqid(),
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => strtoupper($meta['currency'] ?? 'USD'),
            'status' => 'pending',
            'meta' => $meta,
        ]));

        return [
            'transaction' => $transaction,
            'redirect_url' => null,
            'message' => 'Manual payment recorded as pending. Admin confirmation required.',
        ];
    }

    public function confirm(PaymentTransaction $transaction, array $payload = []): PaymentTransaction
    {
        $transaction->status = $payload['status'] ?? 'completed';
        $transaction->meta = array_merge($transaction->meta ?? [], $payload);
        $transaction->save();

        return $transaction->fresh();
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function findWebhookTransaction(Request $request): ?PaymentTransaction
    {
        return null;
    }

    public function webhookStatus(Request $request): string
    {
        return 'pending';
    }
}
