<?php

namespace Tests\Feature;

use App\Enums\PaymentProvider;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPaymentConfirmPopupTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_confirm_uses_popup_with_clear_copy_not_instant_post(): void
    {
        [$admin, $package, $user] = $this->seedAdminPaymentFixtures();

        $memberTx = $this->pendingPayment($user, $package, 'PAY-MEMBER', []);
        $signupTx = $this->pendingPayment($user, $package, 'PAY-SIGNUP', [
            'signup' => [
                'name' => 'New Join',
                'email' => 'new-join@citymax.local',
                'package_id' => $package->id,
                'parent_id' => $user->id,
                'position' => 'left',
                'sponsor_id' => $user->id,
            ],
        ]);
        $powerTx = $this->pendingPayment($user, $package, 'PAY-POWER', [
            'power_activation' => [
                'power_id' => $user->id,
                'name' => 'Power Guest',
                'email' => 'power-guest@citymax.local',
                'package_id' => $package->id,
            ],
        ]);
        $doneTx = PaymentTransaction::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider' => PaymentProvider::Manual,
            'provider_ref' => 'PAY-DONE',
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        $html = $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('js-pay-confirm', false)
            ->assertSee('id="payConfirmModal"', false)
            ->assertSee('Confirm this payment?', false)
            ->assertSee('Yes, mark as paid', false)
            ->assertSee('This cannot be undone from this screen', false)
            ->assertSee('Continue only if the money has actually been received', false)
            ->assertSee('data-kind="member"', false)
            ->assertSee('data-kind="signup"', false)
            ->assertSee('data-kind="power"', false)
            ->assertSee(route('admin.payments.confirm', $memberTx), false)
            ->getContent();

        $this->assertSame(1, preg_match('/type="button"[^>]*class="btn btn-primary btn-sm js-pay-confirm"/', $html));
        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="[^"]*\/payments\/\d+\/confirm"/',
            $html,
            'Confirm must not POST immediately from a row form'
        );
        $this->assertStringContainsString('data-id="'.$memberTx->id.'"', $html);
        $this->assertStringContainsString('data-id="'.$signupTx->id.'"', $html);
        $this->assertStringContainsString('data-id="'.$powerTx->id.'"', $html);
        $this->assertStringContainsString('new-join@citymax.local', $html);
        $this->assertStringContainsString('power-guest@citymax.local', $html);
        $this->assertStringNotContainsString(
            'data-action="'.route('admin.payments.confirm', $doneTx).'"',
            $html
        );
    }

    public function test_admin_confirm_post_still_marks_pending_payment_paid(): void
    {
        Http::fake([
            '*/internal/jobs/place-member*' => Http::response(['ok' => true, 'jobId' => 'job-1'], 202),
        ]);

        [$admin, $package, $user] = $this->seedAdminPaymentFixtures();
        $tx = $this->pendingPayment($user, $package, 'PAY-OK', []);

        $this->actingAs($admin)
            ->from(route('admin.payments.index'))
            ->post(route('admin.payments.confirm', $tx))
            ->assertRedirect(route('admin.payments.index'))
            ->assertSessionHas('success');

        $this->assertSame('completed', $tx->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Package, 2: User}
     */
    private function seedAdminPaymentFixtures(): array
    {
        $package = Package::query()->create([
            'name' => 'Starter',
            'amount' => '100.00',
            'roi_percent' => '1.00',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $admin = User::query()->create([
            'name' => 'Pay Admin',
            'email' => 'pay-admin@citymaxcrypto.com',
            'password' => 'Admin@12345',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Pay User',
            'email' => 'pay-user@citymax.local',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'package_id' => $package->id,
        ]);

        return [$admin, $package, $user];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function pendingPayment(User $user, Package $package, string $ref, array $meta): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'user_id' => $meta === [] ? $user->id : null,
            'package_id' => $package->id,
            'provider' => PaymentProvider::Manual,
            'provider_ref' => $ref,
            'amount' => '100.00',
            'currency' => 'USD',
            'status' => 'pending',
            'meta' => $meta,
        ]);
    }
}
