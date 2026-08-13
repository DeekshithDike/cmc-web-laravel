<?php

namespace App\Contracts;

use App\Enums\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Inbound payments — receive money (package purchase, renewals, top-ups).
 */
interface PaymentGatewayInterface
{
    public function provider(): PaymentProvider;

    /**
     * @return array{transaction: PaymentTransaction, redirect_url?: string|null, message?: string}
     */
    public function initiate(User $user, float $amount, array $meta = []): array;

    public function confirm(PaymentTransaction $transaction, array $payload = []): PaymentTransaction;

    public function verifyWebhook(Request $request): bool;

    public function findWebhookTransaction(Request $request): ?PaymentTransaction;

    /**
     * Map provider webhook payload → pending|completed|failed
     */
    public function webhookStatus(Request $request): string;
}
