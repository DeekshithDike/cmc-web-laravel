<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;

abstract class TestCase extends BaseTestCase
{
    protected const USDT_EVM_ADDRESS = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function actingAs(UserContract $user, $guard = null)
    {
        if ($guard === null && $user instanceof User) {
            $guard = $user->role === UserRole::Admin ? 'admin' : 'customer';
        }

        foreach (['web', 'admin', 'customer'] as $name) {
            Auth::guard($name)->forgetUser();
        }

        return parent::actingAs($user, $guard);
    }

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
