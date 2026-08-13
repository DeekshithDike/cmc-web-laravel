<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Membership\MembershipService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Same pattern as the reference HomeController::testInsertDummyData
 * ("Left 50 & right 50"): a vertical chain on each side under the root.
 *
 * Default: 100 left + 100 right = 200 Power IDs.
 * Does not truncate — run after DatabaseSeeder.
 *
 * php artisan db:seed --class=DummyPowerIdSeeder
 */
class DummyPowerIdSeeder extends Seeder
{
    public function run(): void
    {
        $perSide = (int) config('citymax.seed.dummy_power_ids_per_side', 100);
        if ($perSide < 1) {
            throw new RuntimeException('citymax.seed.dummy_power_ids_per_side must be at least 1.');
        }

        $rootId = (int) config('citymax.seed.customer_id', 3558);
        $root = User::query()
            ->whereKey($rootId)
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->first();

        if (! $root) {
            throw new RuntimeException(
                "Root customer #{$rootId} not found. Run DatabaseSeeder first (php artisan db:seed)."
            );
        }

        /** @var MembershipService $membership */
        $membership = app(MembershipService::class);

        $leftCount = $this->chainSide($membership, (int) $root->id, (int) $root->id, 'left', $perSide);
        $rightCount = $this->chainSide($membership, (int) $root->id, (int) $root->id, 'right', $perSide);

        $this->command?->info("Dummy Power IDs created: {$leftCount} left + {$rightCount} right = ".($leftCount + $rightCount).'.');
    }

    private function chainSide(
        MembershipService $membership,
        int $rootId,
        int $sponsorId,
        string $position,
        int $count,
    ): int {
        $parentId = $rootId;
        $created = 0;

        for ($i = 1; $i <= $count; $i++) {
            $user = $membership->createPowerId($parentId, $sponsorId, $position, false);
            $parentId = (int) $user->id;
            $created++;
        }

        return $created;
    }
}
