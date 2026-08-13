<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const USDT_EVM_ADDRESS = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function assertRedirectedToCredentials($response): string
    {
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/credentials/', $location);

        return $location;
    }

    protected function assertRedirectedToPaymentCheckout($response): string
    {
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_contains($location, '/customer/payment/success')
            || str_contains($location, '/customer/payment/cancel')
            || str_contains($location, 'nowpayments.io/payment'),
            'Expected payment checkout redirect, got '.$location
        );

        return $location;
    }
}
