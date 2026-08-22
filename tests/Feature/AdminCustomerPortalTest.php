<?php

namespace Tests\Feature;

use App\Enums\WithdrawalStatus;
use App\Models\PaymentDetail;
use App\Models\Withdrawal;
use App\Support\IncomeCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class AdminCustomerPortalTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCityMaxPlatform();
    }

    public function test_guest_and_customer_cannot_open_admin_customer_pages(): void
    {
        $this->get(route('admin.customers.dashboard', $this->root))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($this->root)
            ->get(route('admin.customers.dashboard', $this->root))
            ->assertRedirect(route('admin.login'));
        $this->actingAs($this->root)
            ->get(route('admin.customers.tree', $this->root))
            ->assertRedirect(route('admin.login'));
        $this->actingAs($this->root)
            ->get(route('admin.customers.withdrawals.history', $this->root))
            ->assertRedirect(route('admin.login'));
        $this->actingAs($this->root)
            ->get(route('admin.customers.income.history', $this->root))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_the_same_dashboard_data_as_the_customer(): void
    {
        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '1.25',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '1.25',
            'paid_on' => now(IncomeCalendar::timezone())->subDay()->toDateString(),
        ]);

        $customerHtml = $this->actingAs($this->root)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Available balance', false)
            ->assertSee('$200.00', false)
            ->assertSee('Customer ID '.$this->root->id, false)
            ->assertSee($this->root->name, false)
            ->assertSee('$1.25', false)
            ->assertSee('Withdrawal Now', false)
            ->assertSee('Request withdrawal', false)
            ->assertSee('Change Password', false)
            ->assertSee('Log out', false)
            ->getContent();

        $adminHtml = $this->actingAs($this->admin)
            ->get(route('admin.customers.dashboard', $this->root))
            ->assertOk()
            ->assertSee('Available balance', false)
            ->assertSee('$200.00', false)
            ->assertSee('Customer ID '.$this->root->id, false)
            ->assertSee($this->root->name, false)
            ->assertSee('$1.25', false)
            ->assertSee('Active package', false)
            ->assertSee('Starter', false)
            ->assertSee('Expiry', false)
            ->assertSee(\App\Support\IncomeCalendar::formatDate($this->root->expiry_date), false)
            ->assertDontSee('No package', false)
            ->assertDontSee('Withdrawal Now', false)
            ->assertDontSee('Request withdrawal', false)
            ->assertDontSee('Change Password', false)
            ->assertDontSee('Log out', false)
            ->assertSee('Back to admin', false)
            ->assertSee('My Tree', false)
            ->assertSee('Withdrawal History', false)
            ->assertSee('Income History', false)
            ->getContent();

        $this->assertStringContainsString(route('admin.customers.tree', $this->root, false), $adminHtml);
        $this->assertStringContainsString(route('admin.customers.income.history', $this->root, false), $adminHtml);
        $this->assertStringNotContainsString(route('customer.withdrawals.create', false), $adminHtml);
        $this->assertStringContainsString(route('customer.withdrawals.create', false), $customerHtml);
        $this->assertGuest('customer');
    }

    public function test_admin_sees_the_same_tree_and_histories_as_the_customer(): void
    {
        $left = $this->addMember('portal-left@citymaxcrypto.com', 'left');
        $right = $this->addMember('portal-right@citymaxcrypto.com', 'right');

        PaymentDetail::query()->create([
            'user_id' => $this->root->id,
            'roi_amount' => '2.00',
            'binary_amount' => '3.00',
            'referral_amount' => '1.00',
            'total_amount' => '6.00',
            'paid_on' => now()->toDateString(),
        ]);
        PaymentDetail::query()->create([
            'user_id' => $left->id,
            'roi_amount' => '9.99',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '9.99',
            'paid_on' => now()->toDateString(),
        ]);

        Withdrawal::query()->create([
            'user_id' => $this->root->id,
            'amount' => '25.00',
            'fee' => '2.00',
            'payable_amount' => '23.00',
            'wallet_address' => self::USDT_EVM_ADDRESS,
            'status' => WithdrawalStatus::Pending,
            'meta' => ['network' => 'bep20'],
        ]);
        Withdrawal::query()->create([
            'user_id' => $left->id,
            'amount' => '40.00',
            'fee' => '2.00',
            'payable_amount' => '38.00',
            'wallet_address' => self::USDT_EVM_ADDRESS,
            'status' => WithdrawalStatus::Pending,
            'meta' => ['network' => 'bep20'],
        ]);

        $this->actingAs($this->root)->get(route('customer.tree'))
            ->assertOk()
            ->assertSee('Customer ID: '.$this->root->id, false)
            ->assertSee('Add user', false)
            ->assertSee('Customer ID '.$left->id, false);

        $this->actingAs($this->admin)->get(route('admin.customers.tree', $this->root))
            ->assertOk()
            ->assertSee('Customer ID: '.$this->root->id, false)
            ->assertSee('Customer ID '.$left->id, false)
            ->assertDontSee('Add user', false)
            ->assertSee(route('admin.customers.tree.show', ['customer' => $this->root, 'id' => $left->id], false), false);

        $this->actingAs($this->admin)
            ->get(route('admin.customers.tree.show', ['customer' => $this->root, 'id' => $left->id]))
            ->assertOk()
            ->assertSee('Customer ID: '.$left->id, false);

        $this->actingAs($this->admin)
            ->get(route('admin.customers.tree.show', ['customer' => $this->root, 'id' => $right->id]))
            ->assertOk();
        $this->actingAs($this->admin)
            ->get(route('admin.customers.tree.show', ['customer' => $left, 'id' => $right->id]))
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->get(route('admin.customers.tree.show', ['customer' => $this->root, 'id' => $this->admin->id]))
            ->assertNotFound();

        $this->actingAs($this->root)->get(route('customer.income.history'))
            ->assertOk()
            ->assertSee('$2.00', false)
            ->assertSee('$6.00', false)
            ->assertDontSee('$9.99', false);

        $this->actingAs($this->admin)->get(route('admin.customers.income.history', $this->root))
            ->assertOk()
            ->assertSee('$2.00', false)
            ->assertSee('$6.00', false)
            ->assertDontSee('$9.99', false);

        $this->actingAs($this->admin)->get(route('admin.customers.income.history', $left))
            ->assertOk()
            ->assertSee('$9.99', false)
            ->assertDontSee('$6.00', false);

        $this->actingAs($this->root)->get(route('customer.withdrawals.history'))
            ->assertOk()
            ->assertSee('$25.00', false)
            ->assertDontSee('$40.00', false);

        $this->actingAs($this->admin)->get(route('admin.customers.withdrawals.history', $this->root))
            ->assertOk()
            ->assertSee('$25.00', false)
            ->assertDontSee('$40.00', false);
    }

    public function test_admin_customer_pages_are_read_only_and_do_not_login_as_customer(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.customers.dashboard', $this->root))
            ->assertMethodNotAllowed();

        $this->actingAs($this->admin)
            ->get(route('admin.customers.dashboard', $this->root))
            ->assertOk();

        $this->assertAuthenticatedAs($this->admin, 'admin');
        $this->assertGuest('customer');

        $this->actingAs($this->root)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Withdrawal Now', false)
            ->assertSee('Request withdrawal', false);
    }

    public function test_admin_cannot_open_portal_for_missing_or_non_customer_users(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.customers.dashboard', $this->admin))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get('/admin/customers/999999/dashboard')
            ->assertNotFound();
    }

    public function test_active_users_list_links_to_customer_dashboard(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'href="'.route('admin.customers.dashboard', $this->root).'" target="_blank" rel="noopener"',
            $html
        );
        $this->assertStringContainsString('> Dashboard</a>', $html);
    }
}
