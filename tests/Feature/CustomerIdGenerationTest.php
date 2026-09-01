<?php

namespace Tests\Feature;

use App\Models\ReferralIncome;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Support\CustomerIdGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class CustomerIdGenerationTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CustomerIdGenerator::forceNext([]);
        $this->createCityMaxPlatform();
    }

    protected function tearDown(): void
    {
        CustomerIdGenerator::forceNext([]);
        parent::tearDown();
    }

    public function test_new_member_gets_random_four_to_six_digit_id_and_can_login(): void
    {
        $user = $this->placeMember('random-id@test.com', 'left');

        $this->assertTrue(CustomerIdGenerator::isInRange((int) $user->id));
        $this->assertNotSame((int) $this->root->id, (int) $user->id);
        $this->assertSame($this->root->id, $user->parent_id);
        $this->assertSame($this->root->id, $user->sponsor_id);
        $this->assertSame(1, ReferralIncome::query()->where('user_id', $this->root->id)->where('from_user_id', $user->id)->count());
        $this->assertCustomerCanLoginWith((int) $user->id, (string) $user->plain_password);
    }

    public function test_power_id_gets_random_id_and_activation_keeps_it(): void
    {
        $power = app(MembershipService::class)->createPowerId(
            (int) $this->root->id,
            (int) $this->root->id,
            'right',
            false,
        );

        $this->assertTrue(CustomerIdGenerator::isInRange((int) $power->id));
        $this->assertTrue($power->is_power_id);
        $this->assertFalse($power->is_active);

        $activated = app(MembershipService::class)->activatePowerId((int) $power->id, [
            'name' => 'Activated Power',
            'email' => 'activated-power@test.com',
            'package_id' => $this->package->id,
            'password' => 'Act#12',
        ], false);

        $this->assertSame($power->id, $activated->id);
        $this->assertFalse($activated->is_power_id);
        $this->assertTrue($activated->is_active);
        $this->assertCustomerCanLoginWith((int) $activated->id, 'Act#12');
    }

    public function test_id_collision_retries_with_a_new_id(): void
    {
        CustomerIdGenerator::forceNext([(int) $this->root->id, 424242]);

        $user = $this->placeMember('retry-id@test.com', 'left');

        $this->assertSame(424242, (int) $user->id);
        $this->assertDatabaseHas('binary_trees', ['users_id' => 424242]);
        $this->assertDatabaseHas('referral_incomes', [
            'user_id' => $this->root->id,
            'from_user_id' => 424242,
        ]);
    }

    public function test_duplicate_email_is_not_retried_as_a_new_customer_id(): void
    {
        $this->placeMember('same-email@test.com', 'left');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->placeMember('same-email@test.com', 'right');
    }

    public function test_two_members_receive_distinct_ids_in_range(): void
    {
        $left = $this->placeMember('left-id@test.com', 'left');
        $right = $this->placeMember('right-id@test.com', 'right');

        $this->assertTrue(CustomerIdGenerator::isInRange((int) $left->id));
        $this->assertTrue(CustomerIdGenerator::isInRange((int) $right->id));
        $this->assertNotSame($left->id, $right->id);
        $this->assertSame($this->root->id, $left->sponsor_id);
        $this->assertSame($this->root->id, $right->sponsor_id);
    }

    public function test_existing_sequential_login_id_still_works(): void
    {
        $this->root->forceFill(['password' => 'Keep#1'])->save();

        $this->assertCustomerCanLoginWith((int) $this->root->id, 'Keep#1');
    }

    private function placeMember(string $email, string $position): User
    {
        return app(MembershipService::class)->createActiveMember([
            'name' => 'Member '.strtok($email, '@'),
            'email' => $email,
            'password' => 'Mem#12',
            'sponsor_id' => $this->root->id,
            'parent_id' => $this->root->id,
            'position' => $position,
            'package_id' => $this->package->id,
        ], false);
    }

    private function assertCustomerCanLoginWith(int $loginId, string $password): void
    {
        $this->assertTrue(Hash::check($password, User::query()->whereKey($loginId)->value('password')));

        foreach (['web', 'admin', 'customer'] as $guard) {
            Auth::guard($guard)->logout();
        }
        $this->flushSession();

        $this->post(route('customer.login.submit'), [
            'login_id' => $loginId,
            'password' => $password,
        ])->assertRedirect(route('customer.dashboard'));

        $this->assertAuthenticatedAs(User::query()->findOrFail($loginId), 'customer');
    }
}
