<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Middleware\SecureHeaders;
use App\Models\Package;
use App\Models\PaymentDetail;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Auth\OneTimeCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class SecurityAbuseAndIsolationTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createCityMaxPlatform();
    }

    public function test_security_headers_on_public_member_and_admin_pages(): void
    {
        $this->actingAs($this->root)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $csp = (string) $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('form-action', $csp);
        $this->assertStringContainsString('nowpayments.io', $csp);
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $secureRequest = Request::create('/', 'GET');
        $secureRequest->server->set('HTTPS', 'on');
        $response = (new SecureHeaders())->handle(
            $secureRequest,
            fn () => response('ok')
        );

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $response->headers->get('Strict-Transport-Security')
        );
        $this->assertStringContainsString('upgrade-insecure-requests', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_login_register_and_withdrawal_forms_include_csrf(): void
    {
        $this->get(route('customer.login'))->assertOk()->assertSee('name="_token"', false);
        $this->get(route('admin.login'))->assertOk()->assertSee('name="_token"', false);

        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'left',
        ]))->assertOk()->assertSee('name="_token"', false);

        $this->actingAs($this->root)->get(route('customer.withdrawals.create'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="amount"', false)
            ->assertSee('name="wallet_address"', false);

        $this->actingAs($this->root)->get(route('customer.password.edit'))
            ->assertOk()
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_unknown_payment_and_payout_webhooks_are_rejected(): void
    {
        $this->postJson(route('webhooks.payments.handle', 'paypal'), ['payment_status' => 'finished'])
            ->assertNotFound()
            ->assertJson(['ok' => false]);

        $this->postJson(route('webhooks.payouts.handle', 'stripe'), ['id' => 'x'])
            ->assertNotFound()
            ->assertJson(['ok' => false]);
    }

    public function test_customer_cannot_open_admin_or_pay_withdrawals(): void
    {
        $this->actingAs($this->root)->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $this->actingAs($this->root)->post(route('customer.withdrawals.store'), [
            'amount' => 25,
            'wallet_address' => self::USDT_EVM_ADDRESS,
        ])->assertRedirect(route('customer.withdrawals.history'));

        $wd = Withdrawal::query()->latest('id')->firstOrFail();

        $this->actingAs($this->root)
            ->post(route('admin.withdrawals.complete', $wd))
            ->assertRedirect(route('admin.login'));

        $this->assertSame(WithdrawalStatus::Pending, $wd->fresh()->status);
    }

    public function test_tree_is_limited_to_own_downline(): void
    {
        $left = $this->addMember('left-iso@citymaxcrypto.com', 'left');
        $right = $this->addMember('right-iso@citymaxcrypto.com', 'right');

        $this->actingAs($this->root)->get(route('customer.tree.show', $left->id))->assertOk();
        $this->actingAs($left)->get(route('customer.tree'))->assertOk();
        $this->actingAs($left)->get(route('customer.tree.show', $right->id))->assertNotFound();
        $this->actingAs($right)->get(route('customer.tree.show', $this->root->id))->assertNotFound();
        $this->actingAs($this->root)->get(route('customer.tree.show', $this->admin->id))->assertNotFound();
    }

    public function test_xss_names_are_escaped_in_member_ui(): void
    {
        $child = $this->addMember('xss-member@citymaxcrypto.com', 'left', extra: [
            'name' => '<script>alert(1)</script>',
        ]);

        $html = $this->actingAs($this->root)
            ->get(route('customer.tree.show', $child->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_admin_search_does_not_treat_percent_as_match_all(): void
    {
        User::query()->create([
            'name' => 'Normal User',
            'email' => 'wildcard-a@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        User::query()->create([
            'name' => '100% Club',
            'email' => 'wildcard-b@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('wildcard-a@citymaxcrypto.com', false)
            ->assertDontSee('platform-root@citymaxcrypto.com', false);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['q' => 'Club']))
            ->assertOk()
            ->assertSee('wildcard-b@citymaxcrypto.com', false)
            ->assertDontSee('wildcard-a@citymaxcrypto.com', false);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['q' => "' OR 1=1 --"]))
            ->assertOk()
            ->assertDontSee('wildcard-a@citymaxcrypto.com', false);
    }

    public function test_register_ignores_role_and_wallet_mass_assignment(): void
    {
        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'right',
        ]))->assertOk();

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'Spoof Admin',
                'email' => 'spoof-admin@citymaxcrypto.com',
                'phone' => '1111111111',
                'country' => 'MY',
                'package_id' => $this->package->id,
                'role' => 'admin',
                'wallet_balance' => '99999.00',
                'is_active' => true,
                'payment_status' => true,
                'is_power_id' => true,
            ])
        );

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))->assertRedirect();

        $user = User::query()->where('email', 'spoof-admin@citymaxcrypto.com')->firstOrFail();
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertSame('0.00', number_format((float) $user->wallet_balance, 2, '.', ''));
        $this->assertFalse((bool) $user->is_power_id);
    }

    public function test_expired_membership_is_kicked_from_customer_area(): void
    {
        $this->root->update([
            'expiry_date' => now()->subDay()->toDateString(),
            'status' => UserStatus::Expired,
        ]);

        $this->actingAs($this->root)
            ->get(route('customer.dashboard'))
            ->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    public function test_credentials_token_is_one_time_and_unknown_tokens_404(): void
    {
        $token = app(OneTimeCredentials::class)->store((int) $this->root->id, 'TempPass1!');

        $this->get(route('credentials.show', $token))
            ->assertOk()
            ->assertSee('TempPass1!', false)
            ->assertSee((string) $this->root->id, false);

        $this->get(route('credentials.show', $token))->assertNotFound();
        $this->get(route('credentials.show', str_repeat('a', 48)))->assertNotFound();
    }

    public function test_admin_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->from(route('admin.login'))->post(route('admin.login.submit'), [
                'email' => 'throttle-lock@example.com',
                'password' => 'WrongPass1!',
            ]);
        }

        $this->from(route('admin.login'))->post(route('admin.login.submit'), [
            'email' => 'throttle-lock@example.com',
            'password' => 'WrongPass1!',
        ])->assertRedirect(route('admin.login'))->assertSessionHas('error');
    }

    public function test_income_and_withdrawal_history_are_isolated_per_customer(): void
    {
        $left = $this->addMember('iso-left@citymaxcrypto.com', 'left', extra: ['name' => 'Iso Left']);
        $right = $this->addMember('iso-right@citymaxcrypto.com', 'right', extra: ['name' => 'Iso Right']);

        PaymentDetail::query()->create([
            'user_id' => $left->id,
            'roi_amount' => '1.00',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '1.00',
            'paid_on' => now()->toDateString(),
        ]);
        PaymentDetail::query()->create([
            'user_id' => $right->id,
            'roi_amount' => '9.99',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '9.99',
            'paid_on' => now()->toDateString(),
        ]);

        $left->update(['wallet_balance' => '50.00']);
        $this->actingAs($left)->post(route('customer.withdrawals.store'), [
            'amount' => 20,
            'wallet_address' => self::USDT_EVM_ADDRESS,
        ])->assertRedirect(route('customer.withdrawals.history'));

        $this->actingAs($left)->get(route('customer.income.history'))
            ->assertOk()
            ->assertSee('$1.00', false)
            ->assertDontSee('$9.99', false);

        $this->actingAs($right)->get(route('customer.withdrawals.history'))
            ->assertOk()
            ->assertSee('No withdrawals yet', false);
    }

    public function test_inactive_package_cannot_be_purchased(): void
    {
        $inactive = Package::query()->create([
            'name' => 'Retired',
            'amount' => '50.00',
            'roi_percent' => '1.00',
            'is_active' => false,
            'sort_order' => 99,
        ]);

        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'right',
        ]))->assertOk();

        $this->from(route('customer.register'))->post(route('customer.register.save'), [
            'name' => 'Retired Buyer',
            'email' => 'retired-pkg@citymaxcrypto.com',
            'package_id' => $inactive->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertNull(User::query()->where('email', 'retired-pkg@citymaxcrypto.com')->first());
        $this->assertNull(PaymentTransaction::query()->where('meta->signup->email', 'retired-pkg@citymaxcrypto.com')->first());
    }
}
