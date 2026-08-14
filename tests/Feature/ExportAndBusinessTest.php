<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Business\BusinessVolumeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExportAndBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_active_users_csv(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin', 'email' => 'a@t.com', 'password' => 'Admin@12345',
            'role' => UserRole::Admin, 'status' => UserStatus::Active, 'is_active' => true, 'payment_status' => true,
        ]);
        User::query()->create([
            'name' => 'Cust', 'email' => 'c@t.com', 'password' => 'Customer@123',
            'role' => UserRole::Customer, 'status' => UserStatus::Active, 'is_active' => true, 'payment_status' => true,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.export'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_placement_records_business_volume(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 202)]);

        $package = Package::query()->create([
            'name' => 'Starter', 'amount' => '100.00', 'roi_percent' => '1', 'is_active' => true, 'sort_order' => 1,
        ]);
        $root = User::query()->create([
            'name' => 'Root', 'email' => 'r@t.com', 'password' => 'Customer@123',
            'role' => UserRole::Customer, 'status' => UserStatus::Active, 'is_active' => true, 'payment_status' => true,
            'package_id' => $package->id, 'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        BinaryTree::query()->create(['users_id' => $root->id]);

        $admin = User::query()->create([
            'name' => 'Admin', 'email' => 'a@t.com', 'password' => 'Admin@12345',
            'role' => UserRole::Admin, 'status' => UserStatus::Active, 'is_active' => true, 'payment_status' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Left Child',
            'email' => 'l@t.com',
            'sponsor_id' => $root->id,
            'parent_id' => $root->id,
            'position' => 'left',
            'package_id' => $package->id,
        ])->assertRedirect();

        $rows = app(BusinessVolumeService::class)->paginateReportForDate(now()->toDateString());
        $this->assertTrue($rows->contains(fn ($r) => $r['user_id'] === $root->id && $r['left'] === '100.00'));
    }

    public function test_completed_withdrawals_export(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin', 'email' => 'a@t.com', 'password' => 'Admin@12345',
            'role' => UserRole::Admin, 'status' => UserStatus::Active, 'is_active' => true, 'payment_status' => true,
        ]);
        $user = User::query()->create([
            'name' => 'Cust', 'email' => 'c@t.com', 'password' => 'Customer@123',
            'role' => UserRole::Customer, 'status' => UserStatus::Active, 'is_active' => true, 'payment_status' => true,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount' => '25.00',
            'fee' => '5.00',
            'payable_amount' => '20.00',
            'wallet_address' => '0xabc1234567',
            'status' => WithdrawalStatus::Completed,
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.withdrawals.export.completed'))
            ->assertOk();
    }
}
