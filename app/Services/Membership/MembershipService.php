<?php

namespace App\Services\Membership;

use App\Enums\TreePosition;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\User;
use App\Services\Calc\CalcDispatcher;
use App\Services\Business\BusinessVolumeService;
use App\Services\Income\ReferralBonusService;
use App\Support\PostgresIdSequences;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MembershipService
{
    public function __construct(
        private readonly CalcDispatcher $calc,
        private readonly BusinessVolumeService $volumes,
        private readonly ReferralBonusService $referrals,
    ) {
    }

    /**
     * Create a paid, active member and occupy the tree seat.
     * Invite checkout must not call this until payment is confirmed.
     */
    public function createActiveMember(array $data, bool $notifyCalc = true): User
    {
        $user = PostgresIdSequences::run(fn () => DB::transaction(function () use ($data) {
            $package = Package::query()->whereKey($data['package_id'])->where('is_active', true)->firstOrFail();
            $parent = User::query()->whereKey($data['parent_id'])->where('role', UserRole::Customer)->firstOrFail();
            $sponsor = User::query()->whereKey($data['sponsor_id'])->where('role', UserRole::Customer)->firstOrFail();
            $position = TreePosition::from($data['position']);

            $this->assertSlotFree($parent->id, $position);

            $password = $data['password'] ?? $this->generatePassword();

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
                'role' => UserRole::Customer,
                'status' => UserStatus::Active,
                'is_active' => true,
                'payment_status' => true,
                'is_power_id' => false,
                'sponsor_id' => $sponsor->id,
                'parent_id' => $parent->id,
                'position' => $position,
                'package_id' => $package->id,
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
                'expiry_date' => now()->addWeekdays((int) config('citymax.membership.weekdays'))->toDateString(),
                'wallet_balance' => '0.00',
            ]);

            $this->attachToTree($user, $parent, $position);
            $this->volumes->recordPlacementVolume($user, (float) $package->amount);
            $this->referrals->recordForActivation($user);

            $user->plain_password = $password;

            return $user;
        }));

        if ($notifyCalc) {
            $this->calc->placeMember($this->placeMemberPayload($user, 'NORMAL'));
        }

        return $user;
    }

    public function assertInvitePlacementAvailable(int $parentId, string $position): void
    {
        $parent = User::query()->whereKey($parentId)->where('role', UserRole::Customer)->first();
        if (! $parent) {
            throw new InvalidArgumentException('Placement ID not found.');
        }

        $this->assertSlotFree($parentId, TreePosition::from($position));
    }

    public function assertOpenJoinPlacement(int $parentId, string $position): void
    {
        $parent = User::query()->whereKey($parentId)->where('role', UserRole::Customer)->first();
        if (! $parent) {
            throw new InvalidArgumentException('Placement ID not found.');
        }

        if (! $parent->is_active && ! $parent->is_power_id) {
            throw new InvalidArgumentException('Invalid placement ID.');
        }

        $this->assertSlotFree($parentId, TreePosition::from($position));
    }

    public function resolveOpenJoinSponsor(int $sponsorId, int $placementId): int
    {
        $placement = User::query()->whereKey($placementId)->where('role', UserRole::Customer)->first();
        if (! $placement) {
            throw new InvalidArgumentException('Placement ID not found.');
        }

        if ($sponsorId <= 0) {
            if ($placement->is_active && ! $placement->is_power_id) {
                return $placement->id;
            }

            throw new InvalidArgumentException('Sponsor ID is required.');
        }

        $sponsor = User::query()
            ->whereKey($sponsorId)
            ->where('role', UserRole::Customer)
            ->where('is_active', true)
            ->where('is_power_id', false)
            ->first();

        if (! $sponsor) {
            throw new InvalidArgumentException('Sponsor must be an active member ID.');
        }

        if (! $this->isPlacementOrUpline($sponsor->id, $placementId)) {
            throw new InvalidArgumentException('Sponsor must be the placement ID or an upline of the placement ID.');
        }

        return $sponsor->id;
    }

    private function isPlacementOrUpline(int $sponsorId, int $placementId): bool
    {
        $cursor = $placementId;
        $guard = 0;

        while ($cursor > 0 && $guard < 10000) {
            $guard++;
            if ($cursor === $sponsorId) {
                return true;
            }

            $cursor = (int) (User::query()->whereKey($cursor)->value('parent_id') ?? 0);
        }

        return false;
    }

    public function createPowerId(int $parentId, int $sponsorId, string $position, bool $notifyCalc = true): User
    {
        $user = PostgresIdSequences::run(fn () => DB::transaction(function () use ($parentId, $sponsorId, $position) {
            $parent = User::query()->whereKey($parentId)->where('role', UserRole::Customer)->firstOrFail();
            $sponsor = User::query()->whereKey($sponsorId)->where('role', UserRole::Customer)->firstOrFail();
            $pos = TreePosition::from($position);
            $this->assertSlotFree($parent->id, $pos);

            $user = User::query()->create([
                'name' => 'Power ID',
                'email' => 'power+'.Str::lower(Str::random(10)).'@citymax.local',
                'password' => $this->generatePassword(),
                'role' => UserRole::Customer,
                'status' => UserStatus::Inactive,
                'is_active' => false,
                'payment_status' => false,
                'is_power_id' => true,
                'sponsor_id' => $sponsor->id,
                'parent_id' => $parent->id,
                'position' => $pos,
                'wallet_balance' => '0.00',
            ]);

            $this->attachToTree($user, $parent, $pos);

            return $user;
        }));

        if ($notifyCalc) {
            $this->calc->placeMember($this->placeMemberPayload($user, 'DUMMY'));
        }

        return $user;
    }

    public function activatePowerId(int $powerId, array $data, bool $notifyCalc = true): User
    {
        $user = DB::transaction(function () use ($powerId, $data) {
            $user = User::query()
                ->whereKey($powerId)
                ->where('is_power_id', true)
                ->where('is_active', false)
                ->lockForUpdate()
                ->firstOrFail();

            $package = Package::query()->whereKey($data['package_id'])->where('is_active', true)->firstOrFail();
            $password = $data['password'] ?? $this->generatePassword();

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
                'package_id' => $package->id,
                'status' => UserStatus::Active,
                'is_active' => true,
                'payment_status' => true,
                'is_power_id' => false,
                'expiry_date' => now()->addWeekdays((int) config('citymax.membership.weekdays'))->toDateString(),
            ])->save();

            $user->plain_password = $password;

            return $user;
        });

        if ($user->package) {
            $this->volumes->recordPlacementVolume($user, (float) $user->package->amount);
            $this->referrals->recordForActivation($user);
        }

        if ($notifyCalc) {
            $this->calc->placeMember($this->placeMemberPayload($user, 'DUMMY_ACTIVATED'));
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    public function placeMemberPayload(User $user, string $userType): array
    {
        return [
            'userId' => $user->id,
            'parentId' => $user->parent_id,
            'sponsorId' => $user->sponsor_id,
            'position' => $user->position instanceof TreePosition ? $user->position->value : $user->position,
            'packageId' => $user->package_id,
            'userType' => $userType,
        ];
    }

    private function assertSlotFree(int $parentId, TreePosition $position): void
    {
        $parentTree = BinaryTree::query()->where('users_id', $parentId)->lockForUpdate()->first();
        if (! $parentTree) {
            throw new InvalidArgumentException('Placement ID has no tree node.');
        }

        $occupied = $position === TreePosition::Left
            ? $parentTree->left_user_id
            : $parentTree->right_user_id;

        if ($occupied) {
            throw new InvalidArgumentException('Placement position is already taken.');
        }
    }

    private function attachToTree(User $user, User $parent, TreePosition $position): void
    {
        BinaryTree::query()->create([
            'users_id' => $user->id,
            'parent_id' => $parent->id,
            'position' => $position,
        ]);

        $parentTree = BinaryTree::query()->where('users_id', $parent->id)->lockForUpdate()->firstOrFail();
        if ($position === TreePosition::Left) {
            $parentTree->left_user_id = $user->id;
        } else {
            $parentTree->right_user_id = $user->id;
        }
        $parentTree->save();
    }

    private function generatePassword(): string
    {
        $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $symbols = '@#';
        $all = $letters.$digits.$symbols;

        $chars = [
            $letters[random_int(0, strlen($letters) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = count($chars); $i < 6; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
