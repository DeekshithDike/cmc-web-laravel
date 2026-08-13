<?php

namespace App\Services\Business;

use App\Enums\TreePosition;
use App\Enums\UserRole;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BusinessVolumeService
{
    /**
     * Record package volume on every ancestor, on the side the new ID sits under them.
     * Same walk as the reference Node `updateUserTree` after placement.
     */
    public function recordPlacementVolume(User $child, float $amount, ?string $asOf = null): void
    {
        if ($amount <= 0 || ! $child->parent_id || ! $child->position) {
            return;
        }

        $asOf = $asOf ?: now()->toDateString();
        $formatted = number_format($amount, 2, '.', '');
        $fromUserId = (int) $child->id;
        $ancestorId = (int) $child->parent_id;
        $side = $child->position instanceof TreePosition
            ? $child->position
            : TreePosition::from((string) $child->position);

        $guard = 0;
        $seen = [];
        while ($ancestorId > 0 && $guard < 1000) {
            $guard++;
            if (isset($seen[$ancestorId])) {
                break;
            }
            $seen[$ancestorId] = true;
            $this->writeSide($ancestorId, $fromUserId, $formatted, $asOf, $side);

            $ancestor = User::query()->whereKey($ancestorId)->first(['id', 'parent_id', 'position']);
            if (! $ancestor || ! $ancestor->parent_id || ! $ancestor->position) {
                break;
            }

            $side = $ancestor->position instanceof TreePosition
                ? $ancestor->position
                : TreePosition::from((string) $ancestor->position);
            $ancestorId = (int) $ancestor->parent_id;
        }
    }

    /**
     * @return Collection<int, array{user_id:int,name:string,left:string,right:string}>
     */
    public function reportForDate(string $date): Collection
    {
        $left = BinaryTreeLeft::query()
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->whereDate('business_date', $date)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $right = BinaryTreeRight::query()
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->whereDate('business_date', $date)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return $this->mergeTotals($left, $right, true);
    }

    /**
     * @return Collection<int, array{user_id:int,name:string,left:string,right:string}>
     */
    public function reportForRange(string $from, string $to): Collection
    {
        $left = BinaryTreeLeft::query()
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $right = BinaryTreeRight::query()
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return $this->mergeTotals($left, $right, false);
    }

    /**
     * @return array{left_today: string, right_today: string, left_total: string, right_total: string}
     */
    public function volumeForUser(int $userId, ?string $today = null): array
    {
        $today = $today ?: now()->toDateString();

        return [
            'left_today' => $this->sumSide(BinaryTreeLeft::class, $userId, $today),
            'right_today' => $this->sumSide(BinaryTreeRight::class, $userId, $today),
            'left_total' => $this->sumSide(BinaryTreeLeft::class, $userId, null),
            'right_total' => $this->sumSide(BinaryTreeRight::class, $userId, null),
        ];
    }

    /**
     * @return array{total: string, today: string}
     */
    public function platformVolume(?string $today = null): array
    {
        $today = $today ?: now()->toDateString();
        $leftTotal = (float) BinaryTreeLeft::query()->sum('amount');
        $rightTotal = (float) BinaryTreeRight::query()->sum('amount');
        $leftToday = (float) BinaryTreeLeft::query()->whereDate('business_date', $today)->sum('amount');
        $rightToday = (float) BinaryTreeRight::query()->whereDate('business_date', $today)->sum('amount');

        return [
            'total' => number_format($leftTotal + $rightTotal, 2, '.', ''),
            'today' => number_format($leftToday + $rightToday, 2, '.', ''),
        ];
    }

    private function writeSide(int $userId, int $fromUserId, string $amount, string $asOf, TreePosition $side): void
    {
        $payload = [
            'user_id' => $userId,
            'from_user_id' => $fromUserId,
            'amount' => $amount,
            'business_date' => $asOf,
        ];

        if ($side === TreePosition::Left) {
            BinaryTreeLeft::query()->create($payload);
        } else {
            BinaryTreeRight::query()->create($payload);
        }
    }

    private function sumSide(string $model, int $userId, ?string $date): string
    {
        $query = $model::query()->where('user_id', $userId);
        if ($date) {
            $query->whereDate('business_date', $date);
        }

        return number_format((float) $query->sum('amount'), 2, '.', '');
    }

    /**
     * @param  Collection<int|string, mixed>  $left
     * @param  Collection<int|string, mixed>  $right
     * @return Collection<int, array{user_id:int,name:string,left:string,right:string}>
     */
    private function mergeTotals(Collection $left, Collection $right, bool $customersOnly): Collection
    {
        $userIds = $left->keys()->merge($right->keys())->unique()->values();
        if ($userIds->isEmpty()) {
            return collect();
        }

        $namesQuery = User::query()->whereIn('id', $userIds)->select(['id', 'name']);
        if ($customersOnly) {
            $namesQuery->where('role', UserRole::Customer);
        }
        $names = $namesQuery->pluck('name', 'id');

        return $userIds->map(function ($id) use ($left, $right, $names) {
            return [
                'user_id' => (int) $id,
                'name' => (string) ($names[$id] ?? ''),
                'left' => number_format((float) ($left[$id] ?? 0), 2, '.', ''),
                'right' => number_format((float) ($right[$id] ?? 0), 2, '.', ''),
            ];
        })->sortByDesc(fn ($r) => (float) $r['left'] + (float) $r['right'])->values();
    }
}
