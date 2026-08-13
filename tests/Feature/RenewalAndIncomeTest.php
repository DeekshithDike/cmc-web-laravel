<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Services\Income\DailyIncomeService;
use App\Services\Renewals\RenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RenewalAndIncomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_renew_near_expiry_member(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'a@t.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Near',
            'email' => 'n@t.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'expiry_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($admin)->post(route('admin.renewals.renew', $user->id))->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($user->fresh()->expiry_date->gt(now()->addDays(10)));
        $this->assertDatabaseHas('renewal_histories', ['user_id' => $user->id]);
    }

    public function test_daily_income_credits_roi_once(): void
    {
        Http::fake(['*/internal/jobs/daily-income*' => Http::response(['ok' => true], 202)]);

        $package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = User::query()->create([
            'name' => 'Earn',
            'email' => 'e@t.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
            'wallet_balance' => '0.00',
        ]);

        $result = app(DailyIncomeService::class)->run(now()->toDateString());
        $this->assertSame(1, $result['processed']);
        $this->assertEquals('1.00', number_format((float) $user->fresh()->wallet_balance, 2, '.', ''));
        $this->assertSame(1, PaymentDetail::query()->count());

        $again = app(DailyIncomeService::class)->run(now()->toDateString());
        $this->assertSame(0, $again['processed']);
    }

    public function test_invite_register_page_requires_link(): void
    {
        $this->get(route('customer.register'))->assertRedirect(route('landing'));

        $package = Package::query()->create([
            'name' => 'Starter', 'amount' => '100', 'roi_percent' => '1', 'is_active' => true, 'sort_order' => 1,
        ]);
        $root = User::query()->create([
            'name' => 'Root', 'email' => 'r@t.com', 'password' => 'Customer@123',
            'role' => UserRole::Customer, 'status' => UserStatus::Active,
            'is_active' => true, 'payment_status' => true, 'package_id' => $package->id,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        BinaryTree::query()->create(['users_id' => $root->id]);

        $this->get(route('customer.register', [
            'placementID' => $root->id,
            'position' => 'left',
            'sponsorID' => $root->id,
        ]))->assertOk();
    }
}
