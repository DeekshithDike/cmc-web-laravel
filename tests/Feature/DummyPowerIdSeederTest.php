<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CustomerIdGenerator;
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
        $powers = User::query()->where('is_power_id', true)->get();

        $this->assertCount(6, $powers);
        $this->assertTrue($powers->every(fn (User $u) => ! $u->is_active && ! $u->payment_status));
        $this->assertTrue($powers->every(fn (User $u) => CustomerIdGenerator::isInRange((int) $u->id)));

        $left = $this->powerChainFrom($rootId, 'left', 3);
        $right = $this->powerChainFrom($rootId, 'right', 3);

        $this->assertTrue($powers->every(fn (User $u) => (int) $u->sponsor_id === $rootId));
        $this->assertNotSame($left[0]->id, $right[0]->id);
    }

    /**
     * @return list<User>
     */
    private function powerChainFrom(int $rootId, string $position, int $expected): array
    {
        $chain = [];
        $parentId = $rootId;

        for ($i = 0; $i < $expected; $i++) {
            $node = User::query()
                ->where('is_power_id', true)
                ->where('parent_id', $parentId)
                ->where('position', $position)
                ->first();

            $this->assertNotNull($node, "Missing {$position} Power ID under {$parentId}");
            $chain[] = $node;
            $parentId = (int) $node->id;
        }

        return $chain;
    }
}
