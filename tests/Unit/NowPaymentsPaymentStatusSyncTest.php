<?php

namespace Tests\Unit;

use App\Services\Payments\NowPaymentsPaymentGateway;
use Tests\TestCase;

class NowPaymentsPaymentStatusSyncTest extends TestCase
{
    public function test_map_remote_status_matches_official_payment_statuses(): void
    {
        $gateway = app(NowPaymentsPaymentGateway::class);

        $this->assertSame('completed', $gateway->mapRemoteStatus('finished'));
        $this->assertSame('failed', $gateway->mapRemoteStatus('failed'));
        $this->assertSame('failed', $gateway->mapRemoteStatus('refunded'));
        $this->assertSame('failed', $gateway->mapRemoteStatus('expired'));
        $this->assertSame('pending', $gateway->mapRemoteStatus('waiting'));
        $this->assertSame('pending', $gateway->mapRemoteStatus('confirming'));
        $this->assertSame('pending', $gateway->mapRemoteStatus('confirmed'));
        $this->assertSame('pending', $gateway->mapRemoteStatus('sending'));
        $this->assertSame('pending', $gateway->mapRemoteStatus('partially_paid'));
        $this->assertSame('pending', $gateway->mapRemoteStatus('unknown'));
        $this->assertSame('completed', $gateway->mapPayloadStatus(['payment_status' => 'FINISHED']));
    }

    public function test_pick_listed_payment_prefers_original_finished_and_ignores_redeposits(): void
    {
        $gateway = app(NowPaymentsPaymentGateway::class);

        $picked = $gateway->pickListedPayment([
            'data' => [
                [
                    'payment_id' => 11,
                    'parent_payment_id' => 10,
                    'payment_status' => 'finished',
                ],
                [
                    'payment_id' => 10,
                    'parent_payment_id' => null,
                    'payment_status' => 'partially_paid',
                ],
                [
                    'payment_id' => 9,
                    'parent_payment_id' => null,
                    'payment_status' => 'waiting',
                ],
            ],
        ]);

        $this->assertSame(10, $picked['payment_id']);
        $this->assertSame('partially_paid', $picked['payment_status']);

        $finished = $gateway->pickListedPayment([
            'data' => [
                ['payment_id' => 1, 'payment_status' => 'waiting'],
                ['payment_id' => 2, 'payment_status' => 'finished'],
            ],
        ]);
        $this->assertSame(2, $finished['payment_id']);

        $this->assertNull($gateway->pickListedPayment(['data' => []]));
    }
}
