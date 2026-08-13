<?php

namespace App\Contracts;

use App\Enums\PaymentProvider;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

/**
 * Outbound payouts — send crypto (member withdrawals).
 */
interface PayoutGatewayInterface
{
    public function provider(): PaymentProvider;

    /**
     * @return array{withdrawal: Withdrawal, provider_ref?: string|null, message?: string}
     */
    public function send(Withdrawal $withdrawal, array $meta = []): array;

    public function confirm(Withdrawal $withdrawal, array $payload = []): Withdrawal;

    public function verifyWebhook(Request $request): bool;

    public function findWebhookWithdrawal(Request $request): ?Withdrawal;

    public function webhookStatus(Request $request): string;
}
