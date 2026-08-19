<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\CarryForward;
use App\Models\DailyIncomeRun;
use App\Models\Package;
use App\Models\PaymentDetail;
use App\Models\ReferralIncome;
use App\Models\User;
use Database\Seeders\FixManualPackageUpgradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FixManualPackageUpgradeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_repairs_volume_and_referral_and_is_idempotent(): void
    {
        $this->seedMismatch();

        $this->seed(FixManualPackageUpgradeSeeder::class);

        $this->assertSame('5000.00', BinaryTreeLeft::query()->where('from_user_id', 3559)->value('amount'));
        $this->assertSame('1000.00', BinaryTreeLeft::query()->where('from_user_id', 3597)->value('amount'));
        $this->assertSame(28, BinaryTreeRight::query()->where('from_user_id', 3597)->where('amount', '1000.00')->count());
        $this->assertSame('11650.00', CarryForward::query()->where('user_id', 3558)->whereDate('as_of', '2026-08-19')->value('left_carry'));
        $this->assertSame('7150.00', CarryForward::query()->where('user_id', 3559)->whereDate('as_of', '2026-08-19')->value('right_carry'));
        $this->assertSame('5950.00', CarryForward::query()->where('user_id', 3558)->whereDate('as_of', '2026-08-17')->value('left_carry'));
        $this->assertSame('5000.00', ReferralIncome::query()->where('from_user_id', 3559)->value('amount'));
        $this->assertSame('2026-08-20', optional(ReferralIncome::query()->where('from_user_id', 3559)->first()?->earned_on)?->toDateString());
        $this->assertSame(1, ReferralIncome::query()->where('from_user_id', 3597)->where('amount', '100.00')->count());
        $this->assertSame(1, ReferralIncome::query()->where('from_user_id', 3597)->where('amount', '900.00')->whereDate('earned_on', '2026-08-20')->count());
        $this->assertSame('13.00', User::query()->find(3558)?->wallet_balance);
        $this->assertSame(9, (int) User::query()->find(3559)?->package_id);
        $this->assertSame(0, PaymentDetail::query()->count());

        $this->seed(FixManualPackageUpgradeSeeder::class);

        $this->assertSame('11650.00', CarryForward::query()->where('user_id', 3558)->whereDate('as_of', '2026-08-19')->value('left_carry'));
        $this->assertSame(2, ReferralIncome::query()->where('from_user_id', 3597)->count());
    }

    public function test_seeder_uses_next_unpaid_day_when_20_aug_is_already_paid(): void
    {
        $this->seedMismatch();
        DailyIncomeRun::query()->create([
            'as_of' => '2026-08-20',
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'admin',
            'processed' => 1,
            'total_paid' => '1.00',
        ]);

        $this->seed(FixManualPackageUpgradeSeeder::class);

        $this->assertSame('2026-08-21', optional(ReferralIncome::query()->where('from_user_id', 3559)->first()?->earned_on)?->toDateString());
        $this->assertSame('2026-08-21', optional(
            ReferralIncome::query()->where('from_user_id', 3597)->where('amount', '900.00')->first()?->earned_on
        )?->toDateString());
    }

    public function test_seeder_rolls_back_when_package_is_not_the_latest(): void
    {
        $this->seedMismatch();
        User::query()->whereKey(3559)->update(['package_id' => 3]);

        try {
            $this->seed(FixManualPackageUpgradeSeeder::class);
            $this->fail('Seeder should have refused the run.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('3559 must already be Imperial', $e->getMessage());
        }

        $this->assertSame('500.00', BinaryTreeLeft::query()->where('from_user_id', 3559)->value('amount'));
        $this->assertSame('100.00', BinaryTreeLeft::query()->where('from_user_id', 3597)->value('amount'));
        $this->assertSame(2, ReferralIncome::query()->count());
        $this->assertSame('6250.00', CarryForward::query()->where('user_id', 3558)->whereDate('as_of', '2026-08-19')->value('left_carry'));
    }

    public function test_seeder_rolls_back_when_volume_row_count_is_wrong(): void
    {
        $this->seedMismatch();
        BinaryTreeRight::query()->where('from_user_id', 3597)->orderBy('id')->limit(1)->delete();

        try {
            $this->seed(FixManualPackageUpgradeSeeder::class);
            $this->fail('Seeder should have refused the run.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Volume row counts', $e->getMessage());
        }

        $this->assertSame('100.00', BinaryTreeRight::query()->where('from_user_id', 3597)->value('amount'));
        $this->assertSame('6250.00', CarryForward::query()->where('user_id', 3558)->whereDate('as_of', '2026-08-19')->value('left_carry'));
    }

    private function seedMismatch(): void
    {
        $starter = Package::query()->forceCreate([
            'id' => 1,
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $gold = Package::query()->forceCreate([
            'id' => 3,
            'name' => 'Gold',
            'amount' => '500.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 3,
        ]);
        Package::query()->forceCreate([
            'id' => 4,
            'name' => 'Platinum',
            'amount' => '1000.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 4,
        ]);
        Package::query()->forceCreate([
            'id' => 9,
            'name' => 'Imperial',
            'amount' => '5000.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 9,
        ]);

        $this->member(3558, 'Root', $starter->id, '13.00');
        $this->member(3559, 'Angel1', 9, '150.00');
        $this->member(3594, 'Boss01', 9, '255.00');
        $this->member(3595, 'Roopa', $gold->id, '15.00');
        $this->member(3596, 'RMH', $starter->id, '3.00');
        $this->member(3597, 'Areef', 4, '30.00');

        for ($id = 3570; $id <= 3593; $id++) {
            $this->member($id, 'Power '.$id, null, '0.00', true);
        }

        BinaryTreeLeft::query()->create([
            'user_id' => 3558,
            'from_user_id' => 3559,
            'amount' => '500.00',
            'business_date' => '2026-08-16',
        ]);
        BinaryTreeLeft::query()->create([
            'user_id' => 3558,
            'from_user_id' => 3597,
            'amount' => '100.00',
            'business_date' => '2026-08-17',
        ]);

        $rightAncestors = array_merge([3559], range(3570, 3596));
        $this->assertCount(28, $rightAncestors);
        foreach ($rightAncestors as $userId) {
            BinaryTreeRight::query()->create([
                'user_id' => $userId,
                'from_user_id' => 3597,
                'amount' => '100.00',
                'business_date' => '2026-08-17',
            ]);
        }

        ReferralIncome::query()->create([
            'user_id' => 3558,
            'from_user_id' => 3559,
            'amount' => '500.00',
            'earned_on' => '2026-08-16',
        ]);
        ReferralIncome::query()->create([
            'user_id' => 3594,
            'from_user_id' => 3597,
            'amount' => '100.00',
            'earned_on' => '2026-08-17',
        ]);

        CarryForward::query()->create([
            'user_id' => 3558,
            'left_carry' => '5950.00',
            'right_carry' => '0.00',
            'as_of' => '2026-08-17',
        ]);
        CarryForward::query()->create([
            'user_id' => 3558,
            'left_carry' => '6250.00',
            'right_carry' => '0.00',
            'as_of' => '2026-08-19',
        ]);
        foreach ([3559 => '6250.00', 3594 => '1250.00', 3595 => '750.00', 3596 => '650.00'] as $userId => $right) {
            CarryForward::query()->create([
                'user_id' => $userId,
                'left_carry' => '0.00',
                'right_carry' => $right,
                'as_of' => '2026-08-19',
            ]);
        }

        DailyIncomeRun::query()->create([
            'as_of' => '2026-08-17',
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'admin',
            'processed' => 17,
            'total_paid' => '714.50',
        ]);
        DailyIncomeRun::query()->create([
            'as_of' => '2026-08-19',
            'status' => DailyIncomeRun::STATUS_COMPLETED,
            'triggered_by' => 'admin',
            'processed' => 20,
            'total_paid' => '632.50',
        ]);
    }

    private function member(int $id, string $name, ?int $packageId, string $wallet, bool $power = false): User
    {
        return User::query()->forceCreate([
            'id' => $id,
            'name' => $name,
            'email' => $id.'@test.local',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => ! $power,
            'payment_status' => ! $power,
            'is_power_id' => $power,
            'package_id' => $packageId,
            'wallet_balance' => $wallet,
            'expiry_date' => '2027-01-01',
        ]);
    }
}
