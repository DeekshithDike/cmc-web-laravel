<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BinaryTree;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Support\IncomeCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class ActivatedAtTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCityMaxPlatform();
    }

    public function test_admin_create_user_stamps_activated_at_once(): void
    {
        Carbon::setTestNow('2026-03-16 14:30:00');

        $user = $this->addMember('admin-activated@citymaxcrypto.com');

        $this->assertNotNull($user->activated_at);
        $this->assertTrue(Carbon::parse('2026-03-16 14:30:00')->equalTo($user->activated_at));
        $stamped = $user->activated_at->copy();

        $user->forceFill(['expiry_date' => now()->addDays(5)->toDateString()])->save();
        Carbon::setTestNow('2026-03-18 12:00:00');
        $this->actingAs($this->admin)->post(route('admin.renewals.renew', $user->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($stamped->equalTo($user->fresh()->activated_at));

        Carbon::setTestNow();
    }

    public function test_power_id_activation_stamps_activated_at_not_created_at(): void
    {
        Carbon::setTestNow('2026-01-05 10:00:00');
        $dummy = app(MembershipService::class)->createPowerId(
            (int) $this->root->id,
            (int) $this->root->id,
            'left',
            false,
        );

        $this->assertNull($dummy->activated_at);
        $createdAt = $dummy->created_at?->copy();

        Carbon::setTestNow('2026-03-16 09:15:00');
        $this->actingAs($this->admin)->post(route('admin.power.activate.save'), [
            'power_id' => $dummy->id,
            'name' => 'Power Member',
            'email' => 'power-activated@citymaxcrypto.com',
            'package_id' => $this->package->id,
        ])->assertRedirect();

        $activated = $dummy->fresh();
        $this->assertNotNull($activated->activated_at);
        $this->assertTrue(Carbon::parse('2026-03-16 09:15:00')->equalTo($activated->activated_at));
        $this->assertTrue($createdAt?->equalTo($activated->created_at));
        $this->assertFalse($activated->is_power_id);

        Carbon::setTestNow();
    }

    public function test_customer_invite_payment_confirm_stamps_activated_at(): void
    {
        Carbon::setTestNow('2026-04-02 11:00:00');

        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'right',
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'Invite Paid',
                'email' => 'invite-activated@citymaxcrypto.com',
                'phone' => '111',
                'country' => 'MY',
                'package_id' => $this->package->id,
                'parent_id' => $this->root->id,
                'position' => 'right',
                'sponsor_id' => $this->root->id,
            ])
        );

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->assertNull(User::query()->where('email', 'invite-activated@citymaxcrypto.com')->first());

        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'invite-activated@citymaxcrypto.com')->firstOrFail();
        $this->assertNotNull($user->activated_at);
        $this->assertTrue(Carbon::parse('2026-04-02 11:00:00')->equalTo($user->activated_at));

        Carbon::setTestNow();
    }

    public function test_unpaid_member_payment_confirm_stamps_activated_at_once(): void
    {
        Carbon::setTestNow('2026-02-01 08:00:00');
        $unpaid = User::query()->create([
            'name' => 'Unpaid',
            'email' => 'unpaid-activated@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Inactive,
            'is_active' => false,
            'payment_status' => false,
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => 'right',
            'package_id' => $this->package->id,
        ]);
        BinaryTree::query()->create([
            'users_id' => $unpaid->id,
            'parent_id' => $this->root->id,
            'position' => 'right',
        ]);
        $rootTree = BinaryTree::query()->where('users_id', $this->root->id)->firstOrFail();
        $rootTree->right_user_id = $unpaid->id;
        $rootTree->save();

        Carbon::setTestNow('2026-04-10 16:45:00');
        $tx = PaymentTransaction::query()->create([
            'user_id' => $unpaid->id,
            'package_id' => $this->package->id,
            'provider' => PaymentProvider::Manual,
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');

        $activated = $unpaid->fresh();
        $this->assertTrue((bool) $activated->is_active);
        $this->assertNotNull($activated->activated_at);
        $this->assertTrue(Carbon::parse('2026-04-10 16:45:00')->equalTo($activated->activated_at));

        Carbon::setTestNow('2026-04-11 10:00:00');
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx->fresh()));
        $this->assertTrue(Carbon::parse('2026-04-10 16:45:00')->equalTo($unpaid->fresh()->activated_at));

        Carbon::setTestNow();
    }

    public function test_active_users_list_shows_activated_date(): void
    {
        $user = $this->addMember('list-activated@citymaxcrypto.com');

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Activated', false)
            ->assertSee(IncomeCalendar::formatDate($user->fresh()->activated_at), false)
            ->assertSee($user->email, false);
    }
}
