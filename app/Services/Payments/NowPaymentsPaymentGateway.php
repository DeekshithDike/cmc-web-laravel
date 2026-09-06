<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\NowPayments\NowPaymentsClient;
use App\Support\PostgresIdSequences;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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

        $transaction = PostgresIdSequences::run(fn () => PaymentTransaction::query()->create([
            'user_id' => $user?->id,
            'package_id' => $meta['package_id'] ?? $user?->package_id,
            'provider' => PaymentProvider::NowPayments,
            'provider_ref' => $orderId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'status' => 'pending',
            'meta' => array_merge($meta, ['order_id' => $orderId]),
        ]));

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
                'pay_currency' => strtolower((string) config('payments.nowpayments.pay_currency', 'usdtbsc')),
                'order_id' => $orderId,
                'order_description' => $meta['description'] ?? ('City Max Crypto package #'.($transaction->package_id ?? '')),
                'ipn_callback_url' => $ipnUrl,
                'success_url' => $meta['success_url'] ?? $meta['return_url'] ?? url('/customer/dashboard'),
                'cancel_url' => $meta['cancel_url'] ?? url('/customer/login'),
            ];

            $response = $this->client->createInvoice($payload);
            $invoiceId = (string) ($response['id'] ?? $response['invoice_id'] ?? $orderId);
            $invoiceUrl = $response['invoice_url'] ?? $response['invoice_link'] ?? null;

            if (! is_string($invoiceUrl) || $invoiceUrl === '') {
                throw new RuntimeException('NOWPayments did not return a checkout URL.');
            }

            $transaction->provider_ref = $invoiceId;
            $transaction->meta = array_merge($transaction->meta ?? [], [
                'order_id' => $orderId,
                'invoice' => $response,
            ]);
            $transaction->save();

            Log::info('NOWPayments invoice created', [
                'order' => $orderId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
            ]);

            return [
                'transaction' => $transaction->fresh(),
                'redirect_url' => $invoiceUrl,
                'message' => 'NOWPayments invoice created.',
            ];
        } catch (Throwable $e) {
            Log::error('NOWPayments invoice failed', $this->invoiceFailureContext($e, $orderId, $payload ?? []));
            $transaction->status = 'failed';
            $transaction->meta = array_merge($transaction->meta ?? [], [
                'error' => $e->getMessage(),
                'http_status' => $e instanceof RequestException ? $e->response?->status() : null,
                'http_body' => $e instanceof RequestException ? $e->response?->body() : null,
            ]);
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

        return $this->mapRemoteStatus($status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function mapPayloadStatus(array $payload): string
    {
        return $this->mapRemoteStatus((string) ($payload['payment_status'] ?? $payload['status'] ?? ''));
    }

    public function mapRemoteStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'finished' => 'completed',
            'failed', 'refunded', 'expired' => 'failed',
            'partially_paid', 'waiting', 'confirming', 'confirmed', 'sending' => 'pending',
            default => 'pending',
        };
    }

    /**
     * Lookup the current NOWPayments payment for a pending invoice.
     * Prefers GET /payment/{id} when we already have a payment_id; otherwise lists by invoice id.
     *
     * @return array<string, mixed>
     */
    public function fetchPaymentStatus(PaymentTransaction $transaction): array
    {
        $paymentId = $this->storedPaymentId($transaction);
        $lastError = null;

        if ($this->isNowPaymentsId($paymentId)) {
            try {
                $payload = $this->client->getPaymentStatus($paymentId);

                return $this->normalizePaymentPayload($payload);
            } catch (RuntimeException $e) {
                $lastError = $e;
                if (! str_contains(strtolower($e->getMessage()), 'not found')) {
                    throw $e;
                }
            }
        }

        $invoiceId = $this->storedInvoiceId($transaction);
        if (! $this->isNowPaymentsId($invoiceId)) {
            throw $lastError ?? new RuntimeException('NOWPayments invoice id is missing or not a valid numeric invoice id.');
        }

        if (! $this->client->payoutConfigured()) {
            throw new RuntimeException('NOWPayments login is required to look up invoice payments. Set NOWPAYMENTS_EMAIL and NOWPAYMENTS_PASSWORD.');
        }

        $listed = $this->client->listPayments([
            'invoiceId' => $invoiceId,
            'limit' => 50,
            'page' => 0,
        ]);
        $match = $this->pickListedPayment($listed);
        if ($match !== null) {
            return $this->normalizePaymentPayload($match);
        }

        return ['payment_status' => 'waiting', 'invoice_id' => $invoiceId];
    }

    /**
     * @param  array<string, mixed>  $listed
     * @return array<string, mixed>|null
     */
    public function pickListedPayment(array $listed): ?array
    {
        $rows = $listed['data'] ?? $listed['payments'] ?? null;
        if (! is_array($rows)) {
            $rows = isset($listed['payment_id']) ? [$listed] : [];
        }

        $originals = [];
        foreach ($rows as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (filled($item['parent_payment_id'] ?? null)) {
                continue;
            }
            $originals[] = $item;
        }

        if ($originals === []) {
            return null;
        }

        foreach ($originals as $item) {
            if (strtolower((string) ($item['payment_status'] ?? '')) === 'finished') {
                return $item;
            }
        }

        foreach (['failed', 'refunded', 'expired'] as $terminal) {
            foreach ($originals as $item) {
                if (strtolower((string) ($item['payment_status'] ?? '')) === $terminal) {
                    return $item;
                }
            }
        }

        return $originals[0];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePaymentPayload(array $payload): array
    {
        if (isset($payload['payment_id']) || isset($payload['payment_status'])) {
            return $payload;
        }

        $nested = $payload['payment'] ?? $payload['data'] ?? null;
        if (is_array($nested) && (isset($nested['payment_id']) || isset($nested['payment_status']))) {
            return $nested;
        }

        return $payload;
    }

    private function storedPaymentId(PaymentTransaction $transaction): string
    {
        $meta = $transaction->meta ?? [];
        $candidates = [
            $meta['payment_id'] ?? null,
            is_array($meta['webhook'] ?? null) ? ($meta['webhook']['payment_id'] ?? null) : null,
            is_array($meta['payment_status_sync'] ?? null) ? ($meta['payment_status_sync']['payment_id'] ?? null) : null,
        ];

        foreach ($candidates as $value) {
            $id = trim((string) $value);
            if ($this->isNowPaymentsId($id)) {
                return $id;
            }
        }

        return '';
    }

    private function storedInvoiceId(PaymentTransaction $transaction): string
    {
        $meta = $transaction->meta ?? [];
        $candidates = [
            $transaction->provider_ref,
            $meta['invoice_id'] ?? null,
            is_array($meta['invoice'] ?? null) ? ($meta['invoice']['id'] ?? $meta['invoice']['invoice_id'] ?? null) : null,
        ];

        foreach ($candidates as $value) {
            $id = trim((string) $value);
            if ($this->isNowPaymentsId($id)) {
                return $id;
            }
        }

        return '';
    }

    private function isNowPaymentsId(string $id): bool
    {
        return $id !== '' && ctype_digit($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function invoiceFailureContext(Throwable $e, string $orderId, array $payload): array
    {
        $context = [
            'order' => $orderId,
            'type' => $e::class,
            'error' => $e->getMessage(),
            'success_url' => $payload['success_url'] ?? null,
            'cancel_url' => $payload['cancel_url'] ?? null,
            'ipn_callback_url' => $payload['ipn_callback_url'] ?? null,
        ];

        if ($e instanceof RequestException && $e->response) {
            $context['http_status'] = $e->response->status();
            $context['http_body'] = $e->response->body();
        }

        if ($e instanceof ConnectionException) {
            $context['hint'] = 'Could not reach NOWPayments. Check network and NOWPAYMENTS_API_URL.';
        }

        return $context;
    }
}
