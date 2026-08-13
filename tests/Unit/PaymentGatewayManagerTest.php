<?php

namespace Tests\Unit;

use App\Enums\PaymentProvider;
use App\Services\Payments\NowPayments\NowPaymentsClient;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payouts\PayoutGatewayManager;
use Tests\TestCase;

class PaymentGatewayManagerTest extends TestCase
{
    public function test_default_receive_and_payout_drivers_resolve(): void
    {
        config([
            'payments.default_receive' => 'manual',
            'payments.default_payout' => 'manual',
        ]);

        $receive = app(PaymentGatewayManager::class)->driver();
        $payout = app(PayoutGatewayManager::class)->driver();

        $this->assertSame(PaymentProvider::Manual, $receive->provider());
        $this->assertSame(PaymentProvider::Manual, $payout->provider());

        $this->assertSame(
            PaymentProvider::NowPayments,
            app(PaymentGatewayManager::class)->driver(PaymentProvider::NowPayments)->provider()
        );
        $this->assertSame(
            PaymentProvider::NowPayments,
            app(PayoutGatewayManager::class)->driver(PaymentProvider::NowPayments)->provider()
        );
    }

    public function test_nowpayments_ipn_signature_verification(): void
    {
        config(['payments.nowpayments.ipn_secret' => 'ipn-test-secret']);

        $payload = [
            'payment_id' => 1,
            'payment_status' => 'finished',
            'order_id' => 'CMC-1',
        ];
        ksort($payload);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha512', $body, 'ipn-test-secret');

        $client = app(NowPaymentsClient::class);
        $this->assertTrue($client->verifyIpnSignature($body, $sig));
        $this->assertFalse($client->verifyIpnSignature($body, 'bad-signature'));
    }

    public function test_nowpayments_ipn_signature_matches_official_php_sample(): void
    {
        config(['payments.nowpayments.ipn_secret' => 'ipn-test-secret']);

        $payload = [
            'payment_id' => 123456789,
            'parent_payment_id' => 987654321,
            'invoice_id' => null,
            'payment_status' => 'finished',
            'price_amount' => 1,
            'price_currency' => 'usd',
            'order_id' => 'CMC-INV-1',
            'fee' => [
                'currency' => 'btc',
                'depositFee' => 0.1,
                'withdrawalFee' => 0,
                'serviceFee' => 0,
            ],
        ];

        $sorted = $payload;
        ksort($sorted);
        ksort($sorted['fee']);
        $body = json_encode($sorted, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha512', $body, 'ipn-test-secret');

        $this->assertTrue(app(NowPaymentsClient::class)->verifyIpnSignature($body, $sig));
    }

    public function test_nowpayments_totp_is_six_digits(): void
    {
        // Known base32 secret (RFC test vector style); just assert format/stability.
        config(['payments.nowpayments.two_fa_secret' => 'JBSWY3DPEHPK3PXP']);
        $code = app(NowPaymentsClient::class)->generateTotpCode(null, 1_234_567_890);
        $this->assertSame(6, strlen($code));
        $this->assertTrue(ctype_digit($code));
    }
}
