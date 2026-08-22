<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class AdminUserEditTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCityMaxPlatform();
    }

    public function test_active_users_list_has_actions_menu_with_edit(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Actions', false)
            ->assertSee('aria-label="More actions"', false)
            ->assertSee('fa-ellipsis-v', false)
            ->getContent();

        $this->assertStringContainsString(route('admin.users.edit', $this->root, false), $html);
        $this->assertStringContainsString('> Edit</a>', $html);
        $this->assertStringContainsString(
            'href="'.route('admin.customers.dashboard', $this->root).'" target="_blank" rel="noopener"',
            $html
        );
        $this->assertStringContainsString('> Dashboard</a>', $html);
    }

    public function test_admin_can_open_edit_page_with_editable_and_readonly_fields(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $this->root))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee((string) $this->root->id, false)
            ->assertSee('Save profile', false)
            ->assertSee('Update password', false)
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('name="current_password"', false)
            ->assertDontSee('name="package_id"', false)
            ->assertDontSee('name="sponsor_id"', false);
    }

    public function test_admin_can_update_name_and_email(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.users.edit', $this->root))
            ->put(route('admin.users.update', $this->root), [
                'name' => 'Updated Root',
                'email' => 'updated-root@citymaxcrypto.com',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $this->root->id,
            'name' => 'Updated Root',
            'email' => 'updated-root@citymaxcrypto.com',
            'phone' => $this->root->phone,
            'package_id' => $this->root->package_id,
            'wallet_balance' => '200.00',
        ]);
    }

    public function test_keeping_the_same_email_is_allowed(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->root), [
                'name' => 'Same Email Root',
                'email' => $this->root->email,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Same Email Root', $this->root->fresh()->name);
        $this->assertSame('platform-root@citymaxcrypto.com', $this->root->fresh()->email);
    }

    public function test_duplicate_email_is_rejected_like_registration(): void
    {
        User::query()->create([
            'name' => 'Other',
            'email' => 'taken@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.users.edit', $this->root))
            ->put(route('admin.users.update', $this->root), [
                'name' => $this->root->name,
                'email' => 'taken@citymaxcrypto.com',
            ])
            ->assertRedirect(route('admin.users.edit', $this->root))
            ->assertSessionHasErrors('email');

        $this->assertSame('platform-root@citymaxcrypto.com', $this->root->fresh()->email);
    }

    public function test_profile_update_ignores_readonly_fields_in_the_request(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->root), [
                'name' => 'Safe Name',
                'email' => 'safe-name@citymaxcrypto.com',
                'phone' => '9999999999',
                'wallet_balance' => '9999.00',
                'package_id' => 999,
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $this->root->fresh();
        $this->assertSame('Safe Name', $fresh->name);
        $this->assertSame('safe-name@citymaxcrypto.com', $fresh->email);
        $this->assertNotSame('9999999999', (string) $fresh->phone);
        $this->assertSame('200.00', number_format((float) $fresh->wallet_balance, 2, '.', ''));
        $this->assertSame($this->root->package_id, $fresh->package_id);
        $this->assertTrue($fresh->is_active);
    }

    public function test_invalid_profile_payload_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.users.edit', $this->root))
            ->put(route('admin.users.update', $this->root), [
                'name' => '',
                'email' => 'not-an-email',
            ])
            ->assertRedirect(route('admin.users.edit', $this->root))
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_admin_can_change_customer_password_without_current_password(): void
    {
        $oldHash = $this->root->password;

        $this->actingAs($this->admin)
            ->from(route('admin.users.edit', $this->root))
            ->put(route('admin.users.password', $this->root), [
                'password' => 'NewPass1!',
                'password_confirmation' => 'NewPass1!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $fresh = $this->root->fresh();
        $this->assertNotSame($oldHash, $fresh->password);
        $this->assertTrue(Hash::check('NewPass1!', $fresh->password));
        $this->assertFalse(Hash::check('Customer@123', $fresh->password));

        foreach (['web', 'admin', 'customer'] as $guard) {
            Auth::guard($guard)->logout();
        }
        $this->flushSession();

        $this->post(route('customer.login.submit'), [
            'login_id' => $this->root->id,
            'password' => 'Customer@123',
        ])->assertSessionHasErrors();

        $this->post(route('customer.login.submit'), [
            'login_id' => $this->root->id,
            'password' => 'NewPass1!',
        ])->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($fresh, 'customer');
    }

    public function test_weak_or_unconfirmed_password_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.users.edit', $this->root))
            ->put(route('admin.users.password', $this->root), [
                'password' => 'weak',
                'password_confirmation' => 'weak',
            ])
            ->assertRedirect(route('admin.users.edit', $this->root))
            ->assertSessionHasErrors('password');

        $this->actingAs($this->admin)
            ->from(route('admin.users.edit', $this->root))
            ->put(route('admin.users.password', $this->root), [
                'password' => 'NewPass1!',
                'password_confirmation' => 'Mismatch1!',
            ])
            ->assertRedirect(route('admin.users.edit', $this->root))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Customer@123', $this->root->fresh()->password));
    }

    public function test_cannot_edit_admin_or_inactive_customer(): void
    {
        $inactive = User::query()->create([
            'name' => 'Inactive',
            'email' => 'inactive@citymaxcrypto.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Inactive,
            'is_active' => false,
            'payment_status' => false,
        ]);

        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->admin))->assertNotFound();
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => 'Hacked',
            'email' => 'hacked@citymaxcrypto.com',
        ])->assertNotFound();
        $this->actingAs($this->admin)->put(route('admin.users.password', $this->admin), [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertNotFound();

        $this->actingAs($this->admin)->get(route('admin.users.edit', $inactive))->assertNotFound();
        $this->assertSame('Platform Admin', $this->admin->fresh()->name);
        $this->assertTrue(Hash::check('Admin@12345', $this->admin->fresh()->password));
    }

    public function test_guest_and_customer_cannot_edit_users(): void
    {
        $this->get(route('admin.users.edit', $this->root))->assertRedirect(route('admin.login'));
        $this->put(route('admin.users.update', $this->root), [
            'name' => 'Nope',
            'email' => 'nope@citymaxcrypto.com',
        ])->assertRedirect(route('admin.login'));
        $this->put(route('admin.users.password', $this->root), [
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertRedirect(route('admin.login'));

        $this->actingAs($this->root)->get(route('admin.users.edit', $this->root))
            ->assertRedirect(route('admin.login'));
        $this->actingAs($this->root)->put(route('admin.users.update', $this->root), [
            'name' => 'Nope',
            'email' => 'nope@citymaxcrypto.com',
        ])->assertRedirect(route('admin.login'));

        $this->assertSame('Platform Root', $this->root->fresh()->name);
    }
}
