<?php

namespace App\Services\Payouts;

use App\Contracts\PayoutGatewayInterface;
use App\Enums\PaymentProvider;
use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use App\Services\Payments\NowPayments\NowPaymentsClient;
use App\Services\Payments\PaymentEnvironment;
use App\Support\UsdtWalletAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * NOWPayments outbound driver (mass payout / crypto send).
 *
 * Docs: POST /v1/auth + POST /v1/payout + POST /v1/payout/{id}/verify (2FA).
 */
class NowPaymentsPayoutGateway implements PayoutGatewayInterface
{
    public function __construct(private readonly NowPaymentsClient $client)
    {
    }

    public function provider(): PaymentProvider
    {
        return PaymentProvider::NowPayments;
    }

    public function send(Withdrawal $withdrawal, array $meta = []): array
    {
        $currency = strtolower((string) (
            $meta['currency']
            ?? UsdtWalletAddress::nowPaymentsCurrency((string) $withdrawal->wallet_address)
            ?? ($withdrawal->meta['payout_currency'] ?? null)
            ?? config('payments.nowpayments.payout_currency')
        ));
        $amount = (float) $withdrawal->payable_amount;
        $fiatCurrency = strtolower((string) config('payments.nowpayments.payout_fiat_currency'));

        if (! $this->client->payoutConfigured()) {
            if (! PaymentEnvironment::allowsStub()) {
                throw new RuntimeException('NOWPayments payout is not configured. Set API key, email, and password.');
            }

            $withdrawal->payout_provider = PaymentProvider::NowPayments->value;
            $withdrawal->payout_ref = 'NP-STUB-'.uniqid();
            $withdrawal->status = WithdrawalStatus::Completed;
            $withdrawal->remarks = $meta['remarks'] ?? $withdrawal->remarks;
            $withdrawal->processed_at = now();
            $withdrawal->meta = array_merge($withdrawal->meta ?? [], $meta, [
                'stub' => true,
                'currency' => $currency,
            ]);
            $withdrawal->save();

            return [
                'withdrawal' => $withdrawal->fresh(),
                'provider_ref' => $withdrawal->payout_ref,
                'message' => 'NOWPayments payout stub completed (set API key + email/password for live payouts).',
            ];
        }

        try {
            if (config('payments.nowpayments.validate_payout_address', true)) {
                $validation = $this->client->validateAddress(
                    (string) $withdrawal->wallet_address,
                    $currency,
                );
                $explicit = $validation['result'] ?? $validation['valid'] ?? null;
                $invalidExplicit = $explicit === false || $explicit === 0 || $explicit === '0' || $explicit === 'false';
                $status = strtoupper((string) ($validation['status'] ?? 'OK'));
                if ($invalidExplicit || in_array($status, ['ERROR', 'FAILED', 'INVALID'], true)) {
                    $reason = (string) ($validation['message'] ?? $validation['status'] ?? 'invalid address');
                    throw new RuntimeException('NOWPayments rejected wallet address: '.$reason);
                }
            }

            $ipnUrl = route('webhooks.payouts.handle', ['provider' => PaymentProvider::NowPayments->value]);
            $cryptoAmount = round($amount, 6);
            $fiatAmount = round($amount, 2);
            // NOWPayments rejects reused unique_external_id even after a failed payout.
            $externalId = 'CMC-WD-'.$withdrawal->id.'-'.str_replace('.', '', uniqid('', true));
            $payoutItem = [
                'address' => $withdrawal->wallet_address,
                'currency' => $currency,
                'amount' => $cryptoAmount,
                'fiat_amount' => $fiatAmount,
                'fiat_currency' => $fiatCurrency,
                'ipn_callback_url' => $ipnUrl,
                'unique_external_id' => $externalId,
                'payout_description' => 'City Max Crypto withdrawal #'.$withdrawal->id,
            ];
            // extra_id is a blockchain memo/destination tag (XRP, XLM, TON, …).
            // USDT TRC-20 / BEP-20 do not use it; sending our withdrawal id is rejected as
            // "Invalid payout extra ID: USDTBSC <address> <id>".
            $memo = trim((string) ($meta['extra_id'] ?? $withdrawal->meta['extra_id'] ?? ''));
            if ($memo !== '') {
                $payoutItem['extra_id'] = $memo;
            }

            $response = $this->client->createPayout([$payoutItem], $ipnUrl);

            $batchId = (string) ($response['id'] ?? '');
            $item = $response['withdrawals'][0] ?? [];
            $itemId = (string) ($item['id'] ?? $batchId ?: 'NP-'.uniqid());

            $verifyMeta = [];
            if ($this->client->twoFaConfigured() && $batchId !== '') {
                try {
                    $verifyMeta['verify_response'] = $this->client->verifyPayout($batchId);
                } catch (Throwable $verifyError) {
                    Log::error('NOWPayments payout verify failed', [
                        'withdrawal_id' => $withdrawal->id,
                        'batch_id' => $batchId,
                        'error' => $verifyError->getMessage(),
                    ]);
                    throw new RuntimeException(
                        'Payout created but 2FA verify failed: '.$verifyError->getMessage(),
                        0,
                        $verifyError
                    );
                }
            } else {
                Log::warning('NOWPayments payout created without 2FA verify — set NOWPAYMENTS_2FA_SECRET or verify manually within 1 hour', [
                    'withdrawal_id' => $withdrawal->id,
                    'batch_id' => $batchId,
                ]);
                $verifyMeta['verify_skipped'] = true;
            }

            $withdrawal->payout_provider = PaymentProvider::NowPayments->value;
            $withdrawal->payout_ref = $itemId;
            $withdrawal->status = WithdrawalStatus::Processing;
            $withdrawal->remarks = $meta['remarks'] ?? $withdrawal->remarks;
            $withdrawal->meta = array_merge($withdrawal->meta ?? [], [
                'currency' => $currency,
                'batch_id' => $batchId,
                'unique_external_id' => $externalId,
                'payout_response' => $response,
            ], $verifyMeta);
            $withdrawal->save();

            return [
                'withdrawal' => $withdrawal->fresh(),
                'provider_ref' => $itemId,
                'message' => empty($verifyMeta['verify_skipped'])
                    ? 'NOWPayments payout submitted and verified; waiting for IPN confirmation.'
                    : 'NOWPayments payout submitted (2FA verify skipped); waiting for IPN confirmation.',
            ];
        } catch (Throwable $e) {
            Log::error('NOWPayments payout failed', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Unable to send NOWPayments payout: '.$e->getMessage(), 0, $e);
        }
    }

    public function confirm(Withdrawal $withdrawal, array $payload = []): Withdrawal
    {
        $status = WithdrawalStatus::tryFrom((string) ($payload['status'] ?? 'completed'))
            ?? WithdrawalStatus::Completed;

        $withdrawal->status = $status;
        if (! empty($payload['provider_ref'])) {
            $withdrawal->payout_ref = (string) $payload['provider_ref'];
        }
        $withdrawal->meta = array_merge($withdrawal->meta ?? [], $payload);
        if (in_array($status, [WithdrawalStatus::Completed, WithdrawalStatus::Declined], true)) {
            $withdrawal->processed_at = now();
        }
        $withdrawal->save();

        return $withdrawal->fresh();
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

    public function findWebhookWithdrawal(Request $request): ?Withdrawal
    {
        $id = (string) ($request->input('id') ?? '');
        $batchId = (string) ($request->input('batch_withdrawal_id') ?? '');
        $externalId = (string) ($request->input('unique_external_id') ?? '');
        $extraId = (string) ($request->input('extra_id') ?? '');

        $query = Withdrawal::query()->where('payout_provider', PaymentProvider::NowPayments->value);

        if ($id !== '') {
            $found = (clone $query)->where('payout_ref', $id)->first();
            if ($found) {
                return $found;
            }
        }

        if ($batchId !== '') {
            $found = (clone $query)->where('meta->batch_id', $batchId)->first();
            if ($found) {
                return $found;
            }
        }

        if ($externalId !== '') {
            $found = (clone $query)->where('meta->unique_external_id', $externalId)->first();
            if ($found) {
                return $found;
            }
            if (preg_match('/^CMC-WD-(\d+)/', $externalId, $matches) === 1) {
                $found = (clone $query)->whereKey((int) $matches[1])->first();
                if ($found) {
                    return $found;
                }
            }
        }

        if ($extraId !== '' && ctype_digit($extraId)) {
            return (clone $query)->whereKey((int) $extraId)->first();
        }

        return null;
    }

    public function webhookStatus(Request $request): string
    {
        return $this->mapPayloadStatus($request->all(), (string) ($request->input('id') ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function mapPayloadStatus(array $payload, string $payoutRef = ''): string
    {
        $status = '';
        $items = $payload['withdrawals'] ?? null;
        if (is_array($items) && $items !== []) {
            $match = null;
            if ($payoutRef !== '') {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    if ((string) ($item['id'] ?? '') === $payoutRef) {
                        $match = $item;
                        break;
                    }
                }
            }
            $match ??= is_array($items[0] ?? null) ? $items[0] : null;
            if (is_array($match)) {
                $status = (string) ($match['status'] ?? $match['payout_status'] ?? '');
            }
        }
        if ($status === '') {
            $status = (string) ($payload['status'] ?? $payload['payout_status'] ?? '');
        }

        return $this->mapRemoteStatus($status);
    }

    public function mapRemoteStatus(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'FINISHED' => WithdrawalStatus::Completed->value,
            'REJECTED', 'REJECTED_NOT_CHECKED', 'FAILED', 'CANCELLED', 'CANCELED' => WithdrawalStatus::Declined->value,
            'CREATING', 'WAITING', 'PROCESSING', 'SENDING', 'NEW' => WithdrawalStatus::Processing->value,
            default => WithdrawalStatus::Processing->value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPayoutStatus(Withdrawal $withdrawal): array
    {
        $ref = trim((string) $withdrawal->payout_ref);
        $batchId = trim((string) ($withdrawal->meta['batch_id'] ?? ''));
        $ids = [];
        if ($this->isNowPaymentsId($ref)) {
            $ids[] = $ref;
        }
        if ($this->isNowPaymentsId($batchId) && ! in_array($batchId, $ids, true)) {
            $ids[] = $batchId;
        }

        $lastError = null;
        foreach ($ids as $id) {
            try {
                return $this->client->getPayoutStatus($id);
            } catch (RuntimeException $e) {
                $lastError = $e;
                if (! str_contains(strtolower($e->getMessage()), 'not found')) {
                    throw $e;
                }
            }
        }

        if ($this->isNowPaymentsId($batchId)) {
            $listed = $this->client->listPayouts([
                'batch_id' => $batchId,
                'limit' => 50,
                'page' => 0,
            ]);
            $match = $this->matchListedPayout($listed, $ref, $batchId);
            if ($match !== null) {
                return [
                    'id' => $batchId,
                    'withdrawals' => [$match],
                ];
            }
        }

        if ($ids === []) {
            throw new RuntimeException('NOWPayments payout id is missing or not a valid numeric payout id.');
        }

        throw $lastError ?? new RuntimeException('NOWPayments payout was not found.');
    }

    private function isNowPaymentsId(string $id): bool
    {
        return $id !== '' && ctype_digit($id);
    }

    /**
     * @param  array<string, mixed>  $listed
     * @return array<string, mixed>|null
     */
    private function matchListedPayout(array $listed, string $payoutRef, string $batchId): ?array
    {
        $rows = $listed['payouts'] ?? $listed['withdrawals'] ?? [];
        if (! is_array($rows)) {
            return null;
        }

        foreach ($rows as $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemId = (string) ($item['id'] ?? '');
            $itemBatch = (string) ($item['batch_withdrawal_id'] ?? '');
            if (($payoutRef !== '' && $itemId === $payoutRef)
                || ($batchId !== '' && ($itemId === $batchId || $itemBatch === $batchId))) {
                return $item;
            }
        }

        return null;
    }
}
