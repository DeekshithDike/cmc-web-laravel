<?php

namespace Tests\Feature;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_shows_all_plan_packages(): void
    {
        Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('City Max Crypto', false);
        $response->assertSee('Trade crypto with', false);
        $response->assertSee('Member Login', false);
        $response->assertSee('#packages', false);
        $response->assertSee('#income', false);
        foreach ([100, 250, 500, 1000, 1500, 2000, 3000, 4000, 5000] as $amount) {
            $response->assertSee('$'.number_format($amount), false);
        }
        $response->assertSee('1% daily', false);
        $response->assertSee('Tuesday through Saturday', false);
        $response->assertSee('Tue–Sat', false);
        $response->assertSee('10% daily', false);
        $response->assertSee('5% matching', false);
        $response->assertSee('USDT (ERC20)', false);
        $response->assertSee('support@citymaxcrypto.com', false);
        $response->assertSee('landing/css/landing.css', false);
        $response->assertDontSee('fonts.bunny.net', false);
        $response->assertDontSee('fonts.googleapis.com', false);
        $response->assertDontSee('bootstrap.min.css', false);
        $response->assertDontSee('jquery', false);
        $response->assertDontSee('Admin login', false);
        $response->assertDontSee(route('admin.login', absolute: false), false);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_admin_login_remains_reachable_by_direct_url(): void
    {
        $this->get(route('admin.login'))->assertOk();
    }
}
