<?php

namespace App\Services\Payouts;

use App\Contracts\PayoutGatewayInterface;
use App\Enums\PaymentProvider;
use InvalidArgumentException;

class PayoutGatewayManager
{
    /** @var array<string, PayoutGatewayInterface> */
    private array $gateways = [];

    public function __construct(
        ManualPayoutGateway $manual,
        NowPaymentsPayoutGateway $nowPayments,
    ) {
        $this->register($manual);
        $this->register($nowPayments);
    }

    public function driver(?PaymentProvider $provider = null): PayoutGatewayInterface
    {
        $provider ??= PaymentProvider::from(
            (string) config('payments.default_payout', PaymentProvider::Manual->value)
        );

        if (! isset($this->gateways[$provider->value])) {
            throw new InvalidArgumentException("Unsupported payout provider [{$provider->value}].");
        }

        return $this->gateways[$provider->value];
    }

    public function register(PayoutGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->provider()->value] = $gateway;
    }
}
