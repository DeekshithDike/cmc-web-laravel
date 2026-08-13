<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_login_page(): void
    {
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('Customer Login', false);
    }

    public function test_customer_can_login_with_id_and_password(): void
    {
        $customer = User::factory()->customer()->create([
            'password' => 'Customer@123',
            'is_active' => true,
            'payment_status' => true,
            'expiry_date' => now()->addMonths(3)->toDateString(),
        ]);

        $response = $this->post(route('customer.login.submit'), [
            'login_id' => $customer->id,
            'password' => 'Customer@123',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer);
    }

    public function test_expired_customer_cannot_login(): void
    {
        $customer = User::factory()->customer()->expired()->create([
            'password' => 'Customer@123',
            'is_active' => true,
            'payment_status' => true,
        ]);

        $response = $this->from(route('customer.login'))->post(route('customer.login.submit'), [
            'login_id' => $customer->id,
            'password' => 'Customer@123',
        ]);

        $response->assertSessionHasErrors('login_id');
        $this->assertGuest();
    }

    public function test_inactive_customer_cannot_login(): void
    {
        $customer = User::factory()->customer()->create([
            'password' => 'Customer@123',
            'is_active' => false,
            'payment_status' => true,
            'expiry_date' => now()->addMonths(3)->toDateString(),
        ]);

        $response = $this->from(route('customer.login'))->post(route('customer.login.submit'), [
            'login_id' => $customer->id,
            'password' => 'Customer@123',
        ]);

        $response->assertSessionHasErrors('login_id');
        $this->assertGuest();
    }
}
