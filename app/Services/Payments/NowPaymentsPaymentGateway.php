<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\NowPayments\NowPaymentsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * NOWPayments inbound driver (receive crypto).
 *
 * Uses POST /v1/invoice when API key is set (hosted checkout + redirect).
 * Without credentials, creates a local pending stub so flows still work in local/dev.
 */
class NowPaymentsPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly NowPaymentsClient $client)
    {
    }

    public function provider(): PaymentProvider
    {
        return PaymentProvider::NowPayments;
    }

    public function initiate(?User $user, float $amount, array $meta = []): array
    {
        $orderId = (string) ($meta['order_id'] ?? ('CMC-'.($user?->id ?? 'INV').'-'.uniqid()));
        $currency = strtoupper($meta['currency'] ?? config('payments.nowpayments.price_currency'));

        $transaction = PaymentTransaction::query()->create([
            'user_id' => $user?->id,
            'package_id' => $meta['package_id'] ?? $user?->package_id,
            'provider' => PaymentProvider::NowPayments,
            'provider_ref' => $orderId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'status' => 'pending',
            'meta' => array_merge($meta, ['order_id' => $orderId]),
        ]);

        if (! $this->client->configured()) {
            if (! PaymentEnvironment::allowsStub()) {
                $transaction->status = 'failed';
                $transaction->meta = array_merge($transaction->meta ?? [], ['error' => 'NOWPayments is not configured']);
                $transaction->save();

                throw new RuntimeException('NOWPayments is not configured. Set NOWPAYMENTS_API_KEY.');
            }

            $transaction->meta = array_merge($transaction->meta ?? [], ['stub' => true]);
            $transaction->save();

            return [
                'transaction' => $transaction->fresh(),
                'redirect_url' => $meta['return_url'] ?? null,
                'message' => 'NOWPayments stub created (set NOWPAYMENTS_API_KEY to enable live checkout).',
            ];
        }

        try {
            $ipnUrl = route('webhooks.payments.handle', ['provider' => PaymentProvider::NowPayments->value]);
            $payload = [
                'price_amount' => (float) $amount,
                'price_currency' => strtolower((string) config('payments.nowpayments.price_currency')),
                'order_id' => $orderId,
                'order_description' => $meta['description'] ?? ('City Max Crypto package #'.($transaction->package_id ?? '')),
                'ipn_callback_url' => $ipnUrl,
                'success_url' => $meta['success_url'] ?? $meta['return_url'] ?? url('/customer/dashboard'),
                'cancel_url' => $meta['cancel_url'] ?? url('/customer/login'),
            ];

            $response = $this->client->createInvoice($payload);
            $invoiceId = (string) ($response['id'] ?? $response['invoice_id'] ?? $orderId);
            $invoiceUrl = $response['invoice_url'] ?? null;

            $transaction->provider_ref = $invoiceId;
            $transaction->meta = array_merge($transaction->meta ?? [], [
                'order_id' => $orderId,
                'invoice' => $response,
            ]);
            $transaction->save();

            return [
                'transaction' => $transaction->fresh(),
                'redirect_url' => $invoiceUrl,
                'message' => 'NOWPayments invoice created.',
            ];
        } catch (Throwable $e) {
            Log::error('NOWPayments invoice failed', ['error' => $e->getMessage(), 'order' => $orderId]);
            $transaction->status = 'failed';
            $transaction->meta = array_merge($transaction->meta ?? [], ['error' => $e->getMessage()]);
            $transaction->save();

            throw new RuntimeException('Unable to start NOWPayments checkout: '.$e->getMessage(), 0, $e);
        }
    }

    public function confirm(PaymentTransaction $transaction, array $payload = []): PaymentTransaction
    {
        if ($transaction->provider !== PaymentProvider::NowPayments) {
            throw new RuntimeException('Transaction is not a NOWPayments payment.');
        }

        $transaction->status = $payload['status'] ?? 'completed';
        if (! empty($payload['provider_ref'])) {
            $transaction->provider_ref = (string) $payload['provider_ref'];
        }
        $transaction->meta = array_merge($transaction->meta ?? [], $payload);
        $transaction->save();

        return $transaction->fresh();
    }

    public function verifyWebhook(Request $request): bool
    {
        if (! filled(config('payments.nowpayments.ipn_secret'))) {
            return PaymentEnvironment::allowsStub() && ! $this->client->configured();
        }

        return $this->client->verifyIpnSignature(
            $request->getContent(),
            $request->header('x-nowpayments-sig')
        );
    }

    public function findWebhookTransaction(Request $request): ?PaymentTransaction
    {
        $orderId = (string) ($request->input('order_id') ?? '');
        $invoiceId = (string) ($request->input('invoice_id') ?? '');
        $paymentId = (string) ($request->input('payment_id') ?? '');
        $purchaseId = (string) ($request->input('purchase_id') ?? '');

        $refs = array_values(array_unique(array_filter(
            [$orderId, $invoiceId, $paymentId, $purchaseId],
            fn (string $value) => $value !== '' && $value !== '0'
        )));

        $query = PaymentTransaction::query()->where('provider', PaymentProvider::NowPayments);

        foreach ($refs as $ref) {
            $found = (clone $query)->where('provider_ref', $ref)->first()
                ?? (clone $query)->where('meta->order_id', $ref)->first()
                ?? (clone $query)->where('meta->invoice->id', $ref)->first();

            if ($found) {
                return $found;
            }
        }

        return null;
    }

    public function webhookStatus(Request $request): string
    {
        $status = strtolower((string) ($request->input('payment_status') ?? $request->input('status') ?? ''));

        return match ($status) {
            'finished' => 'completed',
            'failed', 'refunded', 'expired' => 'failed',
            'partially_paid', 'waiting', 'confirming', 'confirmed', 'sending' => 'pending',
            default => 'pending',
        };
    }
}
