<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DummyPowerIdSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DummyPowerIdSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dummy_power_id_seeder_builds_left_and_right_chains_like_reference(): void
    {
        config([
            'citymax.seed.admin_password' => 'AdminPass1!',
            'citymax.seed.customer_password' => 'CustomerPass1!',
            'citymax.seed.dummy_power_ids_per_side' => 3,
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DummyPowerIdSeeder::class);

        $rootId = (int) config('citymax.seed.customer_id');
        $powers = User::query()->where('is_power_id', true)->orderBy('id')->get();

        $this->assertCount(6, $powers);
        $this->assertTrue($powers->every(fn (User $u) => ! $u->is_active && ! $u->payment_status));

        $left = $powers->filter(fn (User $u) => $u->position?->value === 'left')->values();
        $right = $powers->filter(fn (User $u) => $u->position?->value === 'right')->values();

        $this->assertCount(3, $left);
        $this->assertCount(3, $right);

        $this->assertSame($rootId, (int) $left[0]->parent_id);
        $this->assertSame((int) $left[0]->id, (int) $left[1]->parent_id);
        $this->assertSame((int) $left[1]->id, (int) $left[2]->parent_id);

        $this->assertSame($rootId, (int) $right[0]->parent_id);
        $this->assertSame((int) $right[0]->id, (int) $right[1]->parent_id);
        $this->assertSame((int) $right[1]->id, (int) $right[2]->parent_id);

        $this->assertTrue($powers->every(fn (User $u) => (int) $u->sponsor_id === $rootId));
    }
}
