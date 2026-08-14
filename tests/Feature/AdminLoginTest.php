<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_login_page(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Admin Login', false);
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
        ]);

        $response = $this->post(route('admin.login.submit'), [
            'email' => 'admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('customer');
    }

    public function test_admin_login_rejects_invalid_password(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.submit'), [
            'email' => 'admin@citymaxcrypto.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_customer_cannot_use_admin_login(): void
    {
        User::factory()->customer()->create([
            'email' => 'member@citymaxcrypto.com',
            'password' => 'Customer@123',
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.submit'), [
            'email' => 'member@citymaxcrypto.com',
            'password' => 'Customer@123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_admin_and_customer_can_stay_logged_in_independently(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
        ]);
        $customer = User::factory()->customer()->create([
            'password' => 'Customer@123',
            'is_active' => true,
            'payment_status' => true,
            'expiry_date' => now()->addMonths(3)->toDateString(),
        ]);

        $this->post(route('admin.login.submit'), [
            'email' => 'admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
        ])->assertRedirect(route('admin.dashboard'));

        $this->post(route('customer.login.submit'), [
            'login_id' => $customer->id,
            'password' => 'Customer@123',
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('customer.dashboard'))->assertOk();
        $this->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('customer.login'))->assertRedirect(route('customer.dashboard'));

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->get(route('customer.dashboard'))->assertOk();
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $this->post(route('customer.logout'))->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
    }
}
