<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BinaryTree;
use App\Models\PaymentTransaction;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Services\Membership\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class OpenRegistrationFlowTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCityMaxPlatform();
    }

    public function test_open_url_is_public_and_shows_editable_placement_and_sponsor(): void
    {
        $html = $this->get(route('customer.register.open'))
            ->assertOk()
            ->assertSee('Open join', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="parent_id"', false)
            ->assertSee('name="position"', false)
            ->assertSee('name="sponsor_id"', false)
            ->assertSee('Full Name', false)
            ->assertSee('Email', false)
            ->assertSee('Phone', false)
            ->assertSee('Country', false)
            ->assertSee('Package', false)
            ->assertDontSee('Invite join', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="\/customer\/register\/open"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*type="number"[^>]*name="parent_id"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*type="number"[^>]*name="sponsor_id"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*type="hidden"[^>]*name="parent_id"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*type="hidden"[^>]*name="sponsor_id"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*name="sponsor_id"[^>]*readonly/',
            $html
        );
    }

    public function test_encrypted_sponsor_in_url_is_prefilled_and_still_editable(): void
    {
        $html = $this->get(route('customer.register.open', [
            'sponsor' => encrypt((string) $this->root->id),
        ]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="sponsor_id"[^>]*value="'.$this->root->id.'"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*name="sponsor_id"[^>]*readonly/',
            $html
        );
        $this->assertStringNotContainsString('name="parent_id" value="'.$this->root->id.'"', $html);
    }

    public function test_invalid_encrypted_sponsor_still_shows_open_form(): void
    {
        $html = $this->get(route('customer.register.open', ['sponsor' => 'not-valid']))
            ->assertOk()
            ->assertSee('Open join', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*name="sponsor_id"[^>]*value="'.$this->root->id.'"/',
            $html
        );
    }

    public function test_dashboard_copy_links_with_and_without_encrypted_sponsor(): void
    {
        $html = $this->actingAs($this->root)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Share registration link', false)
            ->assertSee('Without sponsor', false)
            ->assertSee('With your sponsor ID', false)
            ->assertSee('Copy', false)
            ->getContent();

        $this->assertStringContainsString('id="open-register-url"', $html);
        $this->assertStringContainsString('id="open-register-sponsored-url"', $html);
        $this->assertStringContainsString(route('customer.register.open'), $html);
        $this->assertStringNotContainsString('sponsorID=', $html);
        $this->assertStringNotContainsString('placementID=', $html);

        $this->assertSame(1, preg_match(
            '/id="open-register-sponsored-url"[^>]*value="([^"]+)"/',
            $html,
            $matches
        ));
        $sponsoredUrl = html_entity_decode($matches[1], ENT_QUOTES);
        $query = [];
        parse_str((string) parse_url($sponsoredUrl, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('sponsor', $query);
        $this->assertSame($this->root->id, (int) decrypt($query['sponsor']));
        $this->assertArrayNotHasKey('sponsorID', $query);
        $this->assertArrayNotHasKey('placementID', $query);

        $this->assertSame(1, preg_match(
            '/id="open-register-url"[^>]*value="([^"]+)"/',
            $html,
            $plainMatches
        ));
        $plainUrl = html_entity_decode($plainMatches[1], ENT_QUOTES);
        $this->assertSame(route('customer.register.open'), $plainUrl);
        $this->assertNull(parse_url($plainUrl, PHP_URL_QUERY));
    }

    public function test_submit_without_sponsor_defaults_to_active_placement(): void
    {
        $this->submitOpenJoin('open-default@test.com', [
            'parent_id' => $this->root->id,
            'position' => 'left',
            'sponsor_id' => null,
        ]);

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->assertSame($this->root->id, (int) ($tx->meta['signup']['sponsor_id'] ?? 0));
        $this->assertSame($this->root->id, (int) ($tx->meta['signup']['parent_id'] ?? 0));
        $this->assertSame('left', $tx->meta['signup']['position'] ?? null);
        $this->assertNull(User::query()->where('email', 'open-default@test.com')->first());
    }

    public function test_submit_with_upline_sponsor_and_payment_confirm_places_exactly_there(): void
    {
        $child = $this->addMember('downline@test.com', 'left');

        $this->submitOpenJoin('open-upline@test.com', [
            'parent_id' => $child->id,
            'position' => 'right',
            'sponsor_id' => $this->root->id,
        ]);

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))
            ->assertRedirect()
            ->assertSessionHas('success');

        $user = User::query()->where('email', 'open-upline@test.com')->firstOrFail();
        $this->assertSame($child->id, $user->parent_id);
        $this->assertSame($this->root->id, $user->sponsor_id);
        $this->assertSame('right', $user->position?->value);
        $this->assertDatabaseHas('binary_trees', [
            'users_id' => $child->id,
            'right_user_id' => $user->id,
        ]);
        $this->assertNull(BinaryTree::query()->where('users_id', $child->id)->value('left_user_id'));
        $this->assertSame(1, ReferralIncome::query()->where('user_id', $this->root->id)->where('from_user_id', $user->id)->count());
        $this->assertSame(0, ReferralIncome::query()->where('user_id', $child->id)->where('from_user_id', $user->id)->count());
    }

    public function test_prefilled_sponsor_can_be_changed_to_another_valid_upline(): void
    {
        $child = $this->addMember('place-under@test.com', 'left');

        $this->get(route('customer.register.open', [
            'sponsor' => encrypt((string) $this->root->id),
        ]))->assertOk();

        $this->submitOpenJoin('changed-sponsor@test.com', [
            'parent_id' => $child->id,
            'position' => 'left',
            'sponsor_id' => $child->id,
        ]);

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->assertSame($child->id, (int) ($tx->meta['signup']['sponsor_id'] ?? 0));
        $this->assertSame($child->id, (int) ($tx->meta['signup']['parent_id'] ?? 0));
    }

    public function test_dummy_placement_is_accepted_with_active_upline_sponsor(): void
    {
        $dummy = app(MembershipService::class)->createPowerId($this->root->id, $this->root->id, 'left');

        $this->submitOpenJoin('under-dummy@test.com', [
            'parent_id' => $dummy->id,
            'position' => 'right',
            'sponsor_id' => $this->root->id,
        ]);

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))->assertRedirect();

        $user = User::query()->where('email', 'under-dummy@test.com')->firstOrFail();
        $this->assertSame($dummy->id, $user->parent_id);
        $this->assertSame($this->root->id, $user->sponsor_id);
        $this->assertDatabaseHas('binary_trees', [
            'users_id' => $dummy->id,
            'right_user_id' => $user->id,
        ]);
        $this->assertNull(BinaryTree::query()->where('users_id', $dummy->id)->value('left_user_id'));
    }

    public function test_taken_left_does_not_spill_to_right(): void
    {
        $taken = $this->addMember('taken-left@test.com', 'left');

        $this->from(route('customer.register.open'))
            ->post(route('customer.register.open.save'), $this->openPayload([
                'email' => 'cannot-spill@test.com',
                'parent_id' => $this->root->id,
                'position' => 'left',
                'sponsor_id' => $this->root->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Placement position is already taken.');

        $this->assertNull(User::query()->where('email', 'cannot-spill@test.com')->first());
        $this->assertSame($taken->id, BinaryTree::query()->where('users_id', $this->root->id)->value('left_user_id'));
        $this->assertNull(BinaryTree::query()->where('users_id', $this->root->id)->value('right_user_id'));
        $this->assertSame(0, PaymentTransaction::query()->count());
    }

    public function test_taken_right_does_not_spill_to_left_and_free_side_still_works(): void
    {
        $taken = $this->addMember('taken-right@test.com', 'right');

        $this->from(route('customer.register.open'))
            ->post(route('customer.register.open.save'), $this->openPayload([
                'email' => 'cannot-spill-right@test.com',
                'parent_id' => $this->root->id,
                'position' => 'right',
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Placement position is already taken.');

        $this->assertNull(BinaryTree::query()->where('users_id', $this->root->id)->value('left_user_id'));
        $this->assertSame($taken->id, BinaryTree::query()->where('users_id', $this->root->id)->value('right_user_id'));

        $this->submitOpenJoin('free-left@test.com', [
            'parent_id' => $this->root->id,
            'position' => 'left',
            'sponsor_id' => $this->root->id,
        ]);

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))->assertRedirect();

        $user = User::query()->where('email', 'free-left@test.com')->firstOrFail();
        $this->assertSame($this->root->id, $user->parent_id);
        $this->assertSame('left', $user->position?->value);
        $this->assertSame($taken->id, BinaryTree::query()->where('users_id', $this->root->id)->value('right_user_id'));
    }

    public function test_dummy_sponsor_is_rejected(): void
    {
        $dummy = app(MembershipService::class)->createPowerId($this->root->id, $this->root->id, 'left');

        $this->from(route('customer.register.open'))
            ->post(route('customer.register.open.save'), $this->openPayload([
                'email' => 'dummy-sponsor@test.com',
                'parent_id' => $dummy->id,
                'position' => 'right',
                'sponsor_id' => $dummy->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Sponsor must be an active member ID.');

        $this->assertNull(User::query()->where('email', 'dummy-sponsor@test.com')->first());
        $this->assertSame(0, PaymentTransaction::query()->count());
    }

    public function test_dummy_placement_without_sponsor_is_rejected(): void
    {
        $dummy = app(MembershipService::class)->createPowerId($this->root->id, $this->root->id, 'left');

        $this->from(route('customer.register.open'))
            ->post(route('customer.register.open.save'), $this->openPayload([
                'email' => 'dummy-no-sponsor@test.com',
                'parent_id' => $dummy->id,
                'position' => 'right',
                'sponsor_id' => null,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Sponsor ID is required.');
    }

    public function test_unrelated_sponsor_is_rejected(): void
    {
        $left = $this->addMember('left-leg@test.com', 'left');
        $right = $this->addMember('right-leg@test.com', 'right');

        $this->from(route('customer.register.open'))
            ->post(route('customer.register.open.save'), $this->openPayload([
                'email' => 'bad-sponsor@test.com',
                'parent_id' => $left->id,
                'position' => 'left',
                'sponsor_id' => $right->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Sponsor must be the placement ID or an upline of the placement ID.');

        $this->assertSame(0, PaymentTransaction::query()->count());
    }

    public function test_admin_and_unknown_ids_are_rejected(): void
    {
        $this->from(route('customer.register.open'))
            ->post(route('customer.register.open.save'), $this->openPayload([
                'email' => 'admin-place@test.com',
                'parent_id' => $this->admin->id,
                'position' => 'left',
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Placement ID not found.');

        $this->from(route('customer.register.open'))
            ->post(route('customer.register.open.save'), $this->openPayload([
                'email' => 'missing-place@test.com',
                'parent_id' => 999999,
                'position' => 'left',
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Placement ID not found.');

        $this->from(route('customer.register.open'))
            ->post(route('customer.register.open.save'), $this->openPayload([
                'email' => 'admin-sponsor@test.com',
                'parent_id' => $this->root->id,
                'position' => 'left',
                'sponsor_id' => $this->admin->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Sponsor must be an active member ID.');
    }

    public function test_inactive_non_dummy_placement_is_rejected(): void
    {
        $inactive = User::query()->create([
            'name' => 'Inactive',
            'email' => 'inactive-place@test.com',
            'password' => 'Customer@123',
            'role' => UserRole::Customer,
            'status' => UserStatus::Inactive,
            'is_active' => false,
            'payment_status' => false,
            'is_power_id' => false,
            'parent_id' => $this->root->id,
            'position' => 'left',
        ]);
        BinaryTree::query()->create(['users_id' => $inactive->id, 'parent_id' => $this->root->id, 'position' => 'left']);
        BinaryTree::query()->where('users_id', $this->root->id)->update(['left_user_id' => $inactive->id]);

        $this->from(route('customer.register.open'))
            ->post(route('customer.register.open.save'), $this->openPayload([
                'email' => 'under-inactive@test.com',
                'parent_id' => $inactive->id,
                'position' => 'left',
                'sponsor_id' => $this->root->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Invalid placement ID.');
    }

    public function test_json_submit_returns_checkout_and_json_error(): void
    {
        $this->postJson(route('customer.register.open.save'), $this->openPayload([
            'email' => 'open-json@test.com',
            'parent_id' => $this->root->id,
            'position' => 'right',
            'sponsor_id' => $this->root->id,
        ]))->assertOk()->assertJsonPath('ok', true);

        $this->assertNull(User::query()->where('email', 'open-json@test.com')->first());

        $this->addMember('json-taken@test.com', 'right');

        $this->postJson(route('customer.register.open.save'), $this->openPayload([
            'email' => 'open-json-taken@test.com',
            'parent_id' => $this->root->id,
            'position' => 'right',
            'sponsor_id' => $this->root->id,
        ]))->assertUnprocessable()->assertJson([
            'ok' => false,
            'error' => 'Placement position is already taken.',
        ]);
    }

    public function test_existing_invite_link_is_unchanged(): void
    {
        $dummy = app(MembershipService::class)->createPowerId($this->root->id, $this->root->id, 'left');

        $this->get(route('customer.register'))->assertRedirect(route('landing'));

        $this->get(route('customer.register', [
            'placementID' => $dummy->id,
            'position' => 'right',
        ]))->assertRedirect(route('landing'));

        $html = $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'right',
            'sponsorID' => $this->root->id,
        ]))->assertOk()
            ->assertSee('Invite join', false)
            ->assertDontSee('Open join', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="\/customer\/register"/',
            $html
        );
        $this->assertStringContainsString('type="hidden" name="parent_id"', $html);
        $this->assertStringContainsString('type="hidden" name="sponsor_id"', $html);
        $this->assertStringContainsString('Sponsor ID <strong>#'.$this->root->id.'</strong>', $html);

        $this->actingAs($this->root)
            ->get(route('customer.tree'))
            ->assertOk()
            ->assertSee('placementID%3D'.$this->root->id, false)
            ->assertDontSee('sponsorID', false)
            ->assertDontSee('/customer/register/open', false);
    }

    public function test_existing_invite_still_ignores_spoofed_sponsor(): void
    {
        $thief = $this->addMember('thief-open@test.com', 'right');

        $this->get(route('customer.register', [
            'placementID' => $this->root->id,
            'position' => 'left',
            'sponsorID' => $this->root->id,
        ]))->assertOk();

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.save'), [
                'name' => 'Spoofed',
                'email' => 'spoofed-open@test.com',
                'sponsor_id' => $thief->id,
                'parent_id' => $this->root->id,
                'position' => 'left',
                'package_id' => $this->package->id,
            ])
        );

        $tx = PaymentTransaction::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.payments.confirm', $tx))->assertRedirect();

        $child = User::query()->where('email', 'spoofed-open@test.com')->firstOrFail();
        $this->assertSame($this->root->id, $child->sponsor_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function openPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Open Join',
            'email' => 'open-join@test.com',
            'phone' => '111',
            'country' => 'US',
            'parent_id' => $this->root->id,
            'position' => 'left',
            'package_id' => $this->package->id,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submitOpenJoin(string $email, array $overrides = []): void
    {
        $this->get(route('customer.register.open'))->assertOk();

        $this->assertRedirectedToPaymentCheckout(
            $this->post(route('customer.register.open.save'), $this->openPayload(array_merge([
                'email' => $email,
            ], $overrides)))
        );
    }
}
