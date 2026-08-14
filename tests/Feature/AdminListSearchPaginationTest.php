<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BinaryTree;
use App\Models\BinaryTreeLeft;
use App\Models\Package;
use App\Models\PaymentDetail;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminListSearchPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Package::query()->create([
            'name' => 'Silver',
            'amount' => '250.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->admin = User::query()->create([
            'name' => 'List Admin',
            'email' => 'list-admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);
    }

    public function test_users_search_filter_and_pagination_keep_query_string(): void
    {
        $needle = null;
        for ($i = 1; $i <= 30; $i++) {
            $user = User::query()->create([
                'name' => "Member {$i}",
                'email' => "member{$i}@citymax.local",
                'password' => 'Customer@123',
                'role' => UserRole::Customer,
                'status' => UserStatus::Active,
                'is_active' => true,
                'payment_status' => true,
                'package_id' => $this->package->id,
                'expiry_date' => now()->addMonths(3)->toDateString(),
            ]);
            if ($i === 30) {
                $needle = $user;
            }
        }

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['per_page' => 10]))
            ->assertOk()
            ->assertSee('Showing 1–10 of 30', false)
            ->assertSee('Member 30', false)
            ->assertDontSee('Member 1', false);

        $page2 = $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['per_page' => 10, 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 11–20 of 30', false);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['q' => $needle->id, 'per_page' => 10]))
            ->assertOk()
            ->assertSee($needle->email, false)
            ->assertSee('Showing 1–1 of 1', false)
            ->assertDontSee('Member 1', false);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['q' => 'member30@citymax.local']))
            ->assertOk()
            ->assertSee('Member 30', false)
            ->assertSee('Showing 1–1 of 1', false);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['package_id' => $this->package->id, 'q' => 'Member 30', 'page' => 1]))
            ->assertOk()
            ->assertSee('Member 30', false)
            ->assertSee('Showing 1–1 of 1', false)
            ->assertSee('package_id=1', false);

        $html = $page2->getContent();
        $this->assertStringContainsString('pagination', $html);
        $this->assertStringContainsString('per_page=10', $html);
    }

    public function test_payments_status_filter_and_search_across_pages(): void
    {
        $user = User::query()->create([
            'name' => 'Pay User',
            'email' => 'pay-user@citymax.local',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            PaymentTransaction::query()->create([
                'user_id' => $user->id,
                'package_id' => $this->package->id,
                'provider' => PaymentProvider::Manual,
                'provider_ref' => 'REF-'.$i,
                'amount' => '10.00',
                'currency' => 'USD',
                'status' => $i === 12 ? 'completed' : 'pending',
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index', ['status' => 'completed', 'per_page' => 10]))
            ->assertOk()
            ->assertSee('REF-12', false)
            ->assertDontSee('>REF-1<', false)
            ->assertSee('Showing 1–1 of 1', false);

        $this->actingAs($this->admin)
            ->get(route('admin.payments.index', ['q' => 'Pay User', 'per_page' => 10]))
            ->assertOk()
            ->assertSee('Showing 1–10 of 12', false);

        $page2Html = $this->actingAs($this->admin)
            ->get(route('admin.payments.index', ['q' => 'Pay User', 'page' => 2, 'per_page' => 10]))
            ->assertOk()
            ->assertSee('Showing 11–12 of 12', false)
            ->getContent();
        $this->assertTrue(
            str_contains($page2Html, 'q=Pay%20User') || str_contains($page2Html, 'q=Pay+User'),
            'Pagination links should keep the search query'
        );
    }

    public function test_income_withdrawals_power_and_renewals_search(): void
    {
        $user = User::query()->create([
            'name' => 'Find Me',
            'email' => 'find-me@citymax.local',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addDays(5)->toDateString(),
            'wallet_balance' => '50.00',
        ]);
        BinaryTree::query()->create(['users_id' => $user->id]);

        PaymentDetail::query()->create([
            'user_id' => $user->id,
            'roi_amount' => '1.00',
            'binary_amount' => '0.00',
            'referral_amount' => '0.00',
            'total_amount' => '1.00',
            'paid_on' => now()->subDay()->toDateString(),
        ]);

        Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '20.00',
            'fee' => '2.00',
            'payable_amount' => '18.00',
            'wallet_address' => '0xabc123wallet',
            'status' => WithdrawalStatus::Pending,
        ]);

        $power = User::query()->create([
            'name' => 'Power ID',
            'email' => 'power-list@citymax.local',
            'password' => 'x',
            'role' => UserRole::Customer,
            'status' => UserStatus::Inactive,
            'is_active' => false,
            'payment_status' => false,
            'is_power_id' => true,
            'parent_id' => $user->id,
            'sponsor_id' => $user->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.income.daily', ['q' => 'Find Me']))
            ->assertOk()
            ->assertSee('Find Me', false)
            ->assertSee('Showing 1–1 of 1', false);

        $this->actingAs($this->admin)
            ->get(route('admin.withdrawals.index', ['pending', 'q' => '0xabc123wallet']))
            ->assertOk()
            ->assertSee('0xabc123wallet', false)
            ->assertSee('Yes', false)
            ->assertSee('No', false);

        $this->actingAs($this->admin)
            ->get(route('admin.power.index', ['q' => (string) $power->id]))
            ->assertOk()
            ->assertSee((string) $power->id, false)
            ->assertSee('Showing 1–1 of 1', false);

        $this->actingAs($this->admin)
            ->get(route('admin.renewals.active', ['q' => 'Find Me']))
            ->assertOk()
            ->assertSee('Find Me', false);
    }

    public function test_business_pages_paginate_and_search_server_side(): void
    {
        $users = [];
        for ($i = 1; $i <= 12; $i++) {
            $users[$i] = User::query()->create([
                'name' => "Biz User {$i}",
                'email' => "biz{$i}@citymax.local",
                'password' => 'Customer@123',
                'role' => UserRole::Customer,
                'status' => UserStatus::Active,
                'is_active' => true,
                'payment_status' => true,
                'package_id' => $this->package->id,
            ]);
            BinaryTreeLeft::query()->create([
                'user_id' => $users[$i]->id,
                'from_user_id' => $users[$i]->id,
                'amount' => (string) (100 + $i),
                'business_date' => now()->toDateString(),
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.business.all', [
                'date' => now()->toDateString(),
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertSee('Showing 1–10 of 12', false)
            ->assertSee('Biz User 12', false);

        $this->actingAs($this->admin)
            ->get(route('admin.business.all', [
                'date' => now()->toDateString(),
                'q' => 'Biz User 3',
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertSee('Biz User 3', false)
            ->assertDontSee('Biz User 12', false)
            ->assertSee('Showing 1–1 of 1', false);

        $this->actingAs($this->admin)
            ->get(route('admin.business.offer', [
                'from' => now()->subDay()->toDateString(),
                'to' => now()->toDateString(),
                'q' => (string) $users[7]->id,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertSee('Biz User 7', false)
            ->assertDontSee('Biz User 8', false)
            ->assertSee('Showing 1–1 of 1', false);
    }
}
