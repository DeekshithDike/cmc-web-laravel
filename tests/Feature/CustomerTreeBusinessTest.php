<?php

namespace Tests\Feature;

use App\Services\Membership\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCityMaxPlatform;
use Tests\TestCase;

class CustomerTreeBusinessTest extends TestCase
{
    use CreatesCityMaxPlatform;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createCityMaxPlatform();
    }

    public function test_own_tree_shows_zero_left_and_right_business_when_empty(): void
    {
        $this->actingAs($this->root)
            ->get(route('customer.tree'))
            ->assertOk()
            ->assertSee('Left business', false)
            ->assertSee('Right business', false)
            ->assertSee('$0.00', false);
    }

    public function test_tree_business_sums_active_packages_on_each_leg_of_selected_id(): void
    {
        $left = $this->addMember('left-biz@citymaxcrypto.com', 'left');
        $this->addMember('right-biz@citymaxcrypto.com', 'right');
        $this->addMember('left-grand@citymaxcrypto.com', 'left', $left);

        $this->actingAs($this->root)
            ->get(route('customer.tree'))
            ->assertOk()
            ->assertSee('Left business', false)
            ->assertSee('$200.00', false)
            ->assertSee('$100.00', false);

        $this->actingAs($this->root)
            ->get(route('customer.tree.show', $left->id))
            ->assertOk()
            ->assertSee('Customer ID: '.$left->id, false)
            ->assertSee('$100.00', false)
            ->assertDontSee('$200.00', false);

        $this->actingAs($left)
            ->get(route('customer.tree'))
            ->assertOk()
            ->assertSee('Customer ID: '.$left->id, false)
            ->assertSee('$100.00', false)
            ->assertDontSee('$200.00', false);
    }

    public function test_inactive_power_id_is_not_counted_in_tree_business(): void
    {
        app(MembershipService::class)->createPowerId(
            (int) $this->root->id,
            (int) $this->root->id,
            'left',
            false
        );

        $this->actingAs($this->root)
            ->get(route('customer.tree'))
            ->assertOk()
            ->assertSee('Left business', false)
            ->assertSee('Right business', false)
            ->assertDontSee('$100.00', false);
    }
}
