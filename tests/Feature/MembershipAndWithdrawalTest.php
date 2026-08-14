<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Mail\MemberCredentialsMail;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MembershipAndWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoot(): array
    {
        $package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $root = User::query()->create([
            'name' => 'Root',
            'email' => 'root@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $package->id,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'wallet_balance' => '100.00',
        ]);

        BinaryTree::query()->create(['users_id' => $root->id]);

        return compact('admin', 'root', 'package');
    }

    public function test_admin_can_create_member_under_root(): void
    {
        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true, 'jobId' => 'x'], 202),
        ]);
        Mail::fake();

        ['admin' => $admin, 'root' => $root, 'package' => $package] = $this->seedRoot();

        $this->assertRedirectedToCredentials(
            $this->actingAs($admin)->post(route('admin.users.store'), [
                'name' => 'Child Left',
                'email' => 'child@test.com',
                'sponsor_id' => $root->id,
                'parent_id' => $root->id,
                'position' => 'left',
                'package_id' => $package->id,
            ])
        );

        $this->assertDatabaseHas('users', ['email' => 'child@test.com', 'parent_id' => $root->id]);
        $this->assertDatabaseHas('binary_trees', ['users_id' => $root->id, 'left_user_id' => User::where('email', 'child@test.com')->value('id')]);
        Mail::assertSent(MemberCredentialsMail::class, function (MemberCredentialsMail $mail) {
            return $mail->hasTo('child@test.com');
        });
    }

    public function test_withdrawal_rejects_invalid_usdt_address(): void
    {
        ['root' => $root] = $this->seedRoot();

        $this->actingAs($root)->from(route('customer.withdrawals.create'))->post(route('customer.withdrawals.store'), [
            'amount' => 25,
            'wallet_address' => 'not-a-wallet',
        ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_withdrawal_accepts_trc20_and_bep20_addresses(): void
    {
        ['root' => $root] = $this->seedRoot();

        $this->actingAs($root)->post(route('customer.withdrawals.store'), [
            'amount' => 25,
            'wallet_address' => 'TEmGwPeRTPiLFLVfBxXkSP91yc5GMNQhfS',
        ])->assertRedirect(route('customer.withdrawals.history'));

        $trc = Withdrawal::query()->latest('id')->firstOrFail();
        $this->assertSame('trc20', $trc->meta['network'] ?? null);
        $this->assertSame('usdttrc20', $trc->meta['payout_currency'] ?? null);

        $root->update(['wallet_balance' => '100.00']);

        $this->actingAs($root)->post(route('customer.withdrawals.store'), [
            'amount' => 25,
            'wallet_address' => self::USDT_EVM_ADDRESS,
        ])->assertRedirect(route('customer.withdrawals.history'));

        $bep = Withdrawal::query()->latest('id')->firstOrFail();
        $this->assertSame('bep20', $bep->meta['network'] ?? null);
        $this->assertSame('usdtbsc', $bep->meta['payout_currency'] ?? null);
    }

    public function test_customer_can_request_and_admin_can_complete_withdrawal(): void
    {
        ['admin' => $admin, 'root' => $root] = $this->seedRoot();

        $this->actingAs($root)->post(route('customer.withdrawals.store'), [
            'amount' => 25,
            'wallet_address' => self::USDT_EVM_ADDRESS,
        ])->assertRedirect(route('customer.withdrawals.history'));

        $withdrawal = Withdrawal::query()->first();
        $this->assertSame(WithdrawalStatus::Pending, $withdrawal->status);
        $this->assertEquals('75.00', number_format((float) $root->fresh()->wallet_balance, 2, '.', ''));

        $this->actingAs($admin)
            ->from(route('admin.withdrawals.index', 'pending'))
            ->post(route('admin.withdrawals.complete', $withdrawal))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(WithdrawalStatus::Completed, $withdrawal->fresh()->status);
    }
}
