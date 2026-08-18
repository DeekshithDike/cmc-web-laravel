<?php

namespace Tests\Feature;

use App\Mail\MemberCredentialsMail;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Membership\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class GeneratedPasswordFormatTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCityMaxPlatform();
        Mail::fake();
    }

    public function test_admin_created_member_password_is_six_chars_letters_digits_at_or_hash(): void
    {
        $location = $this->assertRedirectedToCredentials(
            $this->actingAs($this->admin)->post(route('admin.users.store'), [
                'name' => 'Admin Child',
                'email' => 'admin-child@test.com',
                'sponsor_id' => $this->root->id,
                'parent_id' => $this->root->id,
                'position' => 'left',
                'package_id' => $this->package->id,
            ])
        );

        $user = User::query()->where('email', 'admin-child@test.com')->firstOrFail();
        $password = $this->passwordFromMail('admin-child@test.com');

        $this->assertGeneratedLoginPassword($password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSeePasswordOnCredentialsPage($location, $password);
        $this->assertCustomerCanLoginWith((int) $user->id, $password);
    }

    public function test_invite_link_member_password_matches_admin_created_pattern(): void
    {
        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'right',
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'Invite Child',
                'email' => 'invite-child@test.com',
                'package_id' => $this->package->id,
                'parent_id' => $this->root->id,
                'position' => 'right',
            ])
        );

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'invite-child@test.com')->firstOrFail();
        $password = $this->passwordFromMail('invite-child@test.com');
        $token = $tx->fresh()->meta['credentials_token'] ?? null;

        $this->assertGeneratedLoginPassword($password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertNotEmpty($token);
        $this->assertSeePasswordOnCredentialsPage(route('credentials.show', ['token' => $token]), $password);
        $this->assertCustomerCanLoginWith((int) $user->id, $password);
    }

    public function test_admin_activated_power_id_uses_the_same_password_pattern(): void
    {
        $power = app(MembershipService::class)->createPowerId(
            (int) $this->root->id,
            (int) $this->root->id,
            'left',
            false
        );

        $location = $this->assertRedirectedToCredentials(
            $this->actingAs($this->admin)->post(route('admin.power.activate.save'), [
                'power_id' => $power->id,
                'name' => 'Power Member',
                'email' => 'power-child@test.com',
                'package_id' => $this->package->id,
            ])
        );

        $user = User::query()->where('email', 'power-child@test.com')->firstOrFail();
        $password = $this->passwordFromMail('power-child@test.com');

        $this->assertGeneratedLoginPassword($password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSeePasswordOnCredentialsPage($location, $password);
        $this->assertCustomerCanLoginWith((int) $user->id, $password);
    }

    private function assertGeneratedLoginPassword(string $password): void
    {
        $this->assertSame(6, strlen($password));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9@#]{6}$/', $password);
        $this->assertMatchesRegularExpression('/[A-Za-z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
        $this->assertMatchesRegularExpression('/[@#]/', $password);
    }

    private function passwordFromMail(string $email): string
    {
        $password = null;

        Mail::assertSent(MemberCredentialsMail::class, function (MemberCredentialsMail $mail) use ($email, &$password) {
            if (! $mail->hasTo($email)) {
                return false;
            }

            $password = $mail->password;

            return true;
        });

        $this->assertIsString($password);

        return $password;
    }

    private function assertSeePasswordOnCredentialsPage(string $url, string $password): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee($password, false);
    }

    private function assertCustomerCanLoginWith(int $loginId, string $password): void
    {
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
