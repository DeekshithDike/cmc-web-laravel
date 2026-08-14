<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Http;

trait CreatesCityMaxPlatform
{
    protected Package $package;

    protected User $admin;

    protected User $root;

    protected function createCityMaxPlatform(array $root = []): void
    {
        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true, 'jobId' => 'platform-place'], 202),
            '*/internal/jobs/daily-income*' => Http::response(['ok' => true, 'jobId' => 'platform-income'], 202),
        ]);

        config([
            'payments.default_receive' => 'manual',
            'payments.default_payout' => 'manual',
            'payments.nowpayments.api_key' => null,
            'payments.nowpayments.ipn_secret' => null,
        ]);

        $this->package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->root = User::query()->create(array_merge([
            'name' => 'Platform Root',
            'email' => 'platform-root@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $this->package->id,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'wallet_balance' => '200.00',
        ], $root));

        BinaryTree::query()->create(['users_id' => $this->root->id]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function addMember(string $email, string $position = 'left', ?User $parent = null, array $extra = []): User
    {
        $parent = $parent ?? $this->root;
        $payload = array_merge([
            'name' => 'Member '.strtok($email, '@'),
            'email' => $email,
            'sponsor_id' => $parent->id,
            'parent_id' => $parent->id,
            'position' => $position,
            'package_id' => $this->package->id,
        ], $extra);

        $this->actingAs($this->admin)->post(route('admin.users.store'), $payload);

        return User::query()->where('email', $email)->firstOrFail();
    }
}
