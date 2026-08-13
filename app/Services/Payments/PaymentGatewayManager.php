<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentProvider;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, PaymentGatewayInterface> */
    private array $gateways = [];

    public function __construct(
        ManualPaymentGateway $manual,
        NowPaymentsPaymentGateway $nowPayments,
    ) {
        $this->register($manual);
        $this->register($nowPayments);
    }

    public function driver(?PaymentProvider $provider = null): PaymentGatewayInterface
    {
        $provider ??= PaymentProvider::from(
            (string) config('payments.default_receive', PaymentProvider::Manual->value)
        );

        if (! isset($this->gateways[$provider->value])) {
            throw new InvalidArgumentException("Unsupported payment provider [{$provider->value}].");
        }

        return $this->gateways[$provider->value];
    }

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->provider()->value] = $gateway;
    }
}
