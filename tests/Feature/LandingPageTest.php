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
        $response->assertSee('City Max Crypto Malaysia', false);
        $response->assertSee('Daily ROI &amp; USDT Packages in Malaysia', false);
        $response->assertSee('name="description"', false);
        $response->assertSee('name="keywords"', false);
        $response->assertSee('citymax crypto Malaysia', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('geo.region', false);
        $response->assertSee('content="MY"', false);
        $response->assertSee('hreflang="en-MY"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('Malaysia. All rights reserved.', false);
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
        $response->assertSee('USDT (TRC20)', false);
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
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString('https://nowpayments.io', $csp);
        $this->assertStringNotContainsString('upgrade-insecure-requests', $csp);
    }

    public function test_robots_and_sitemap_are_published_for_google(): void
    {
        $robots = $this->get('/robots.txt');
        $robots->assertOk();
        $robots->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $robots->assertSee('Allow: /', false);
        $robots->assertSee('Disallow: /admin', false);
        $robots->assertSee('Sitemap:', false);

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertOk();
        $sitemap->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $sitemap->assertSee('<loc>', false);
        $sitemap->assertSee(url('/'), false);
    }

    public function test_member_and_admin_pages_are_not_indexed(): void
    {
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('noindex,nofollow', false);

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('noindex,nofollow', false);
    }

    public function test_admin_login_remains_reachable_by_direct_url(): void
    {
        $this->get(route('admin.login'))->assertOk();
    }
}
