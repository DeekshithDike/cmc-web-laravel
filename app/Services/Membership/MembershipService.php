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

    public function createActiveMember(array $data, bool $awaitingPayment = false): User
    {
        $user = DB::transaction(function () use ($data, $awaitingPayment) {
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
                'status' => $awaitingPayment ? UserStatus::Inactive : UserStatus::Active,
                'is_active' => ! $awaitingPayment,
                'payment_status' => ! $awaitingPayment,
                'is_power_id' => false,
                'sponsor_id' => $sponsor->id,
                'parent_id' => $parent->id,
                'position' => $position,
                'package_id' => $package->id,
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
                'expiry_date' => $awaitingPayment
                    ? null
                    : now()->addWeekdays((int) config('citymax.membership.weekdays', 150))->toDateString(),
                'wallet_balance' => '0.00',
            ]);

            $this->attachToTree($user, $parent, $position);

            if (! $awaitingPayment) {
                $this->volumes->recordPlacementVolume($user, (float) $package->amount);
                $this->referrals->creditForActivation($user);
            }

            $user->plain_password = $password;

            return $user;
        });

        if (! $awaitingPayment) {
            $this->calc->placeMember([
                'userId' => $user->id,
                'parentId' => $user->parent_id,
                'sponsorId' => $user->sponsor_id,
                'position' => $user->position instanceof TreePosition ? $user->position->value : $user->position,
                'packageId' => $user->package_id,
                'userType' => 'NORMAL',
            ]);
        }

        return $user;
    }

    public function createPowerId(int $parentId, int $sponsorId, string $position): User
    {
        $user = DB::transaction(function () use ($parentId, $sponsorId, $position) {
            $parent = User::query()->whereKey($parentId)->where('role', UserRole::Customer)->firstOrFail();
            $sponsor = User::query()->whereKey($sponsorId)->where('role', UserRole::Customer)->firstOrFail();
            $pos = TreePosition::from($position);
            $this->assertSlotFree($parent->id, $pos);

            $user = User::query()->create([
                'name' => 'Power ID',
                'email' => 'power+'.Str::lower(Str::random(10)).'@citymax.local',
                'password' => Str::password(16),
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
        });

        $this->calc->placeMember([
            'userId' => $user->id,
            'parentId' => $user->parent_id,
            'sponsorId' => $user->sponsor_id,
            'position' => $user->position instanceof TreePosition ? $user->position->value : $user->position,
            'userType' => 'DUMMY',
        ]);

        return $user;
    }

    public function activatePowerId(int $powerId, array $data): User
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
                'expiry_date' => now()->addWeekdays((int) config('citymax.membership.weekdays', 150))->toDateString(),
            ])->save();

            $user->plain_password = $password;

            return $user->fresh();
        });

        if ($user->package) {
            $this->volumes->recordPlacementVolume($user, (float) $user->package->amount);
            $this->referrals->creditForActivation($user);
        }

        $this->calc->placeMember([
            'userId' => $user->id,
            'parentId' => $user->parent_id,
            'sponsorId' => $user->sponsor_id,
            'position' => $user->position instanceof TreePosition ? $user->position->value : $user->position,
            'packageId' => $user->package_id,
            'userType' => 'DUMMY_ACTIVATED',
        ]);

        return $user;
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
        return Str::password(12);
    }
}
