<?php

namespace App\Services\Payments\NowPayments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for the official NOWPayments API used by this app:
 * receive invoice + IPN, send payout + IPN.
 *
 * Docs: https://documenter.getpostman.com/view/7907941/2s93JusNJt
 * Receive: POST /invoice + IPN (x-nowpayments-sig HMAC-SHA512)
 * Send:    POST /auth → JWT, POST /payout, POST /payout/{id}/verify (2FA)
 */
class NowPaymentsClient
{
    public function configured(): bool
    {
        return filled(config('payments.nowpayments.api_key'));
    }

    public function payoutConfigured(): bool
    {
        return $this->configured()
            && filled(config('payments.nowpayments.email'))
            && filled(config('payments.nowpayments.password'));
    }

    public function twoFaConfigured(): bool
    {
        return filled(config('payments.nowpayments.two_fa_secret'));
    }

    public function createInvoice(array $payload): array
    {
        return $this->request()->post($this->url('/invoice'), $payload)->throw()->json();
    }

    public function validateAddress(string $address, string $currency, ?string $extraId = null): array
    {
        $body = [
            'address' => $address,
            'currency' => strtolower($currency),
        ];
        if ($extraId !== null && $extraId !== '') {
            $body['extra_id'] = $extraId;
        }

        return $this->request()->post($this->url('/payout/validate-address'), $body)->throw()->json();
    }

    public function createPayout(array $withdrawals, ?string $ipnCallbackUrl = null): array
    {
        $body = ['withdrawals' => $withdrawals];
        if ($ipnCallbackUrl) {
            $body['ipn_callback_url'] = $ipnCallbackUrl;
        }

        return $this->request()
            ->withToken($this->bearerToken())
            ->post($this->url('/payout'), $body)
            ->throw()
            ->json();
    }

    /**
     * Required by NOWPayments: payouts stay CREATING until verified with 2FA.
     *
     * @see POST /v1/payout/{batch-withdrawal-id}/verify
     */
    public function verifyPayout(string $batchWithdrawalId, ?string $verificationCode = null): array
    {
        $code = $verificationCode ?? $this->generateTotpCode();

        return $this->request()
            ->withToken($this->bearerToken())
            ->post($this->url('/payout/'.$batchWithdrawalId.'/verify'), [
                'verification_code' => $code,
            ])
            ->throw()
            ->json();
    }

    public function verifyIpnSignature(?string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('payments.nowpayments.ipn_secret');
        if ($secret === '' || $rawBody === null || $signature === null || $signature === '') {
            return false;
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            return false;
        }

        $sorted = $this->sortRecursive($data);
        $encoded = json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return false;
        }

        $hmac = hash_hmac('sha512', $encoded, trim($secret));

        return hash_equals($hmac, $signature);
    }

    public function generateTotpCode(?string $secret = null, ?int $timestamp = null): string
    {
        $secret = $secret ?? (string) config('payments.nowpayments.two_fa_secret');
        if ($secret === '') {
            throw new RuntimeException('NOWPayments 2FA secret is not configured (NOWPAYMENTS_2FA_SECRET).');
        }

        $key = $this->base32Decode($secret);
        if ($key === '') {
            throw new RuntimeException('NOWPayments 2FA secret is invalid base32.');
        }

        $time = intdiv($timestamp ?? time(), 30);
        $binaryTime = pack('N*', 0, $time);
        $hash = hash_hmac('sha1', $binaryTime, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % 1_000_000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function bearerToken(): string
    {
        $cacheKey = 'nowpayments.jwt';

        return Cache::remember($cacheKey, now()->addMinutes((int) config('payments.nowpayments.jwt_cache_minutes', 4)), function () {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('payments.nowpayments.auth_timeout', 20))
                ->post($this->url('/auth'), [
                    'email' => config('payments.nowpayments.email'),
                    'password' => config('payments.nowpayments.password'),
                ])
                ->throw()
                ->json();

            $token = $response['token'] ?? null;
            if (! is_string($token) || $token === '') {
                throw new RuntimeException('NOWPayments auth did not return a token.');
            }

            return $token;
        });
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('NOWPayments API key is not configured.');
        }

        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('payments.nowpayments.http_timeout', 30))
            ->withHeaders([
                'x-api-key' => (string) config('payments.nowpayments.api_key'),
            ]);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('payments.nowpayments.base_url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sortRecursive(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortRecursive($value);
            }
        }

        return $data;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/\s+/', '', $secret) ?? '');
        $secret = rtrim($secret, '=');
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $val = strpos($map, $secret[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
