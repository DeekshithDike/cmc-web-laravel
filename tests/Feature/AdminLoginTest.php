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
        $this->assertAuthenticatedAs($admin);
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
        $this->assertGuest();
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
        $this->assertGuest();
    }
}
