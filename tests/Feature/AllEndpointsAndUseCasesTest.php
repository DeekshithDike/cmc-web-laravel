<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Hits every public/admin/customer endpoint and the main user-role scenarios.
 */
class AllEndpointsAndUseCasesTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private User $admin;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true, 'jobId' => 'ep-place'], 202),
            '*/internal/jobs/daily-income*' => Http::response(['ok' => true, 'jobId' => 'ep-income'], 202),
        ]);

        $this->package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Endpoint Admin',
            'email' => 'ep-admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->root = User::query()->create([
            'name' => 'Endpoint Root',
            'email' => 'ep-root@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'wallet_balance' => '200.00',
        ]);

        BinaryTree::query()->create(['users_id' => $this->root->id]);
    }

    public function test_guest_public_pages_and_auth_guards(): void
    {
        $this->get('/')->assertOk()->assertSee('City Max Crypto', false);
        $this->get('/up')->assertOk();
        $this->get(route('admin.login'))->assertOk();
        $this->get(route('customer.login'))->assertOk();
        $this->get(route('customer.register'))->assertRedirect(route('landing'));

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));

        $this->postJson(route('webhooks.payments.handle', 'unknown'))
            ->assertStatus(404)
            ->assertJson(['ok' => false]);
        $this->postJson(route('webhooks.payouts.handle', 'unknown'))
            ->assertStatus(404)
            ->assertJson(['ok' => false]);
    }

    public function test_role_boundaries(): void
    {
        $this->actingAs($this->root)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('customer.dashboard'))->assertForbidden();

        $expired = User::query()->create([
            'name' => 'Expired',
            'email' => 'expired-ep@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Expired,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($expired)->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
    }

    public function test_admin_can_open_every_admin_page(): void
    {
        $this->actingAs($this->admin);

        foreach ([
            route('admin.dashboard'),
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.payments.index'),
            route('admin.income.daily'),
            route('admin.power.index'),
            route('admin.power.activate'),
            route('admin.withdrawals.index', 'pending'),
            route('admin.withdrawals.index', 'processing'),
            route('admin.withdrawals.index', 'completed'),
            route('admin.withdrawals.index', 'declined'),
            route('admin.business.all'),
            route('admin.business.offer'),
            route('admin.renewals.active'),
            route('admin.renewals.renewed'),
            route('admin.renewals.expired'),
            route('admin.password.edit'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get(route('admin.users.index', ['q' => $this->root->id]))->assertOk();
        $this->get(route('admin.users.export'))->assertOk()->assertHeader('content-disposition');
        $this->get(route('admin.withdrawals.export.completed'))->assertOk();
    }

    public function test_admin_member_payment_income_power_and_business_flow(): void
    {
        $this->assertRedirectedToCredentials(
            $this->actingAs($this->admin)->post(route('admin.users.store'), [
                'name' => 'Left Child',
                'email' => 'left-ep@citymaxcrypto.com',
                'sponsor_id' => $this->root->id,
                'parent_id' => $this->root->id,
                'position' => 'left',
                'package_id' => $this->package->id,
            ])
        );

        $this->actingAs($this->admin)->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'name' => 'Taken Seat',
            'email' => 'taken-ep@citymaxcrypto.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($this->admin)->post(route('admin.payments.start'), [
            'user_id' => $this->root->id,
            'amount' => 100,
            'package_id' => $this->package->id,
        ])->assertRedirect()->assertSessionHas('success');

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('completed', $tx->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.income.daily.run'), [
            'as_of' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($this->admin)->post(route('admin.power.store'), [
            'parent_id' => $this->root->id,
            'sponsor_id' => $this->root->id,
            'position' => 'right',
        ])->assertRedirect()->assertSessionHas('success');

        $power = User::query()->where('is_power_id', true)->firstOrFail();
        $this->assertRedirectedToCredentials(
            $this->actingAs($this->admin)->post(route('admin.power.activate.save'), [
                'power_id' => $power->id,
                'name' => 'Activated Power',
                'email' => 'power-ep@citymaxcrypto.com',
                'phone' => '5555555555',
                'country' => 'US',
                'package_id' => $this->package->id,
            ])
        );

        $this->assertDatabaseHas('users', [
            'email' => 'power-ep@citymaxcrypto.com',
            'is_power_id' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->get(route('admin.business.all', [
            'date' => now()->toDateString(),
        ]))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.business.offer', [
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
        ]))->assertOk();
    }

    public function test_renewal_and_password_and_logout(): void
    {
        $near = User::query()->create([
            'name' => 'Near Expiry',
            'email' => 'near-ep@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($this->admin)->post(route('admin.renewals.renew', $near->id), [
            'notes' => 'e2e renew',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($near->fresh()->expiry_date->gt(now()->addDays(10)));

        $this->actingAs($this->admin)->put(route('admin.password.update'), [
            'current_password' => 'Admin@12345',
            'password' => 'Admin@99999',
            'password_confirmation' => 'Admin@99999',
        ])->assertRedirect()->assertSessionHas('success');

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();

        $this->post(route('admin.login.submit'), [
            'email' => $this->admin->email,
            'password' => 'Admin@99999',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_customer_pages_tree_withdrawals_and_password(): void
    {
        Http::fake(['*/internal/jobs/*' => Http::response(['ok' => true], 202)]);

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Tree Child',
            'email' => 'tree-ep@citymaxcrypto.com',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ])->assertRedirect();

        $child = User::query()->where('email', 'tree-ep@citymaxcrypto.com')->firstOrFail();

        $this->actingAs($this->root)->get(route('customer.dashboard'))->assertOk();
        $this->actingAs($this->root)->get(route('customer.tree'))->assertOk();
        $this->actingAs($this->root)->get(route('customer.tree.show', $child->id))->assertOk();
        $this->actingAs($this->root)->get(route('customer.tree.show', $this->admin->id))->assertForbidden();
        $this->actingAs($this->root)->get(route('customer.income.history'))->assertOk();
        $this->actingAs($this->root)->get(route('customer.withdrawals.create'))->assertOk();
        $this->actingAs($this->root)->get(route('customer.withdrawals.history'))->assertOk();
        $this->actingAs($this->root)->get(route('customer.password.edit'))->assertOk();

        $this->actingAs($this->root)->from(route('customer.withdrawals.create'))->post(route('customer.withdrawals.store'), [
            'amount' => 5,
            'wallet_address' => '0xabc1234567',
        ])->assertRedirect()->assertSessionHas('error');

        $this->actingAs($this->root)->post(route('customer.withdrawals.store'), [
            'amount' => 25,
            'wallet_address' => '0xabc1234567890def',
        ])->assertRedirect(route('customer.withdrawals.history'));

        $wd = Withdrawal::query()->latest('id')->firstOrFail();
        $this->assertSame(WithdrawalStatus::Pending, $wd->status);

        $this->actingAs($this->admin)->from(route('admin.withdrawals.index'))->post(route('admin.withdrawals.decline', $wd), [
            'remarks' => 'e2e decline',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(WithdrawalStatus::Declined, $wd->fresh()->status);

        $this->actingAs($this->root)->put(route('customer.password.update'), [
            'current_password' => 'Customer@123',
            'password' => 'Customer@999',
            'password_confirmation' => 'Customer@999',
        ])->assertRedirect()->assertSessionHas('success');

        $this->post(route('customer.logout'))->assertRedirect(route('customer.login'));
        $this->post(route('customer.login.submit'), [
            'login_id' => $this->root->id,
            'password' => 'Customer@999',
        ])->assertRedirect(route('customer.dashboard'));
    }

    public function test_invite_register_and_manual_payment_confirm(): void
    {
        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'right',
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->assertRedirectedToCredentials(
            $this->post(route('customer.register.save'), [
                'name' => 'Invitee EP',
                'email' => 'invite-ep@citymaxcrypto.com',
                'phone' => '9999999999',
                'country' => 'US',
                'sponsor_id' => $this->root->id,
                'parent_id' => $this->root->id,
                'position' => 'right',
                'package_id' => $this->package->id,
            ])
        );

        $invitee = User::query()->where('email', 'invite-ep@citymaxcrypto.com')->firstOrFail();
        $tx = PaymentTransaction::query()->where('user_id', $invitee->id)->firstOrFail();
        $this->assertSame('pending', $tx->status);

        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('completed', $tx->fresh()->status);
        $this->assertTrue((bool) $invitee->fresh()->is_active);
    }
}
