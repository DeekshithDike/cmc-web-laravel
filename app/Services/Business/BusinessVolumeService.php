<?php

namespace App\Services\Business;

use App\Enums\TreePosition;
use App\Enums\UserRole;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\User;
use App\Support\AdminList;
use App\Support\IncomeCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

        $asOf = $asOf ?: IncomeCalendar::today();
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
     * @return LengthAwarePaginator<int, array{user_id:int,name:string,left:string,right:string}>
     */
    public function paginateReportForDate(string $date, string $q = '', int $perPage = AdminList::PER_PAGE): LengthAwarePaginator
    {
        return $this->paginateMergedVolumes(
            leftBuilder: fn ($query) => $query->whereDate('business_date', $date),
            rightBuilder: fn ($query) => $query->whereDate('business_date', $date),
            q: $q,
            perPage: $perPage,
            customersOnly: true,
        );
    }

    /**
     * @return LengthAwarePaginator<int, array{user_id:int,name:string,left:string,right:string}>
     */
    public function paginateReportForRange(string $from, string $to, string $q = '', int $perPage = AdminList::PER_PAGE): LengthAwarePaginator
    {
        return $this->paginateMergedVolumes(
            leftBuilder: function ($query) use ($from, $to) {
                $query->whereDate('business_date', '>=', $from)
                    ->whereDate('business_date', '<=', $to);
            },
            rightBuilder: function ($query) use ($from, $to) {
                $query->whereDate('business_date', '>=', $from)
                    ->whereDate('business_date', '<=', $to);
            },
            q: $q,
            perPage: $perPage,
            customersOnly: false,
        );
    }

    /**
     * @return array{left_today: string, right_today: string, left_total: string, right_total: string}
     */
    public function volumeForUser(int $userId, ?string $today = null): array
    {
        $today = $today ?: IncomeCalendar::today();

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
        $today = $today ?: IncomeCalendar::today();
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
     * @param  callable(\Illuminate\Database\Query\Builder):void  $leftBuilder
     * @param  callable(\Illuminate\Database\Query\Builder):void  $rightBuilder
     * @return LengthAwarePaginator<int, array{user_id:int,name:string,left:string,right:string}>
     */
    private function paginateMergedVolumes(callable $leftBuilder, callable $rightBuilder, string $q, int $perPage, bool $customersOnly): LengthAwarePaginator
    {
        $leftQuery = DB::table('binary_tree_lefts')
            ->select('user_id', DB::raw('SUM(amount) as left_total'), DB::raw('0 as right_total'))
            ->groupBy('user_id');
        $leftBuilder($leftQuery);

        $rightQuery = DB::table('binary_tree_rights')
            ->select('user_id', DB::raw('0 as left_total'), DB::raw('SUM(amount) as right_total'))
            ->groupBy('user_id');
        $rightBuilder($rightQuery);

        $union = $leftQuery->unionAll($rightQuery);

        $aggregated = DB::query()
            ->fromSub($union, 'side_volumes')
            ->select(
                'user_id',
                DB::raw('SUM(left_total) as left_total'),
                DB::raw('SUM(right_total) as right_total')
            )
            ->groupBy('user_id');

        $query = DB::query()
            ->fromSub($aggregated, 'volumes')
            ->join('users', 'users.id', '=', 'volumes.user_id')
            ->select(
                'volumes.user_id',
                'users.name',
                'volumes.left_total',
                'volumes.right_total'
            )
            ->orderByRaw('(volumes.left_total + volumes.right_total) DESC')
            ->orderBy('volumes.user_id');

        if ($customersOnly) {
            $query->where('users.role', UserRole::Customer->value);
        }

        if ($q !== '') {
            if (AdminList::isNumericId($q)) {
                $query->where('volumes.user_id', (int) $q);
            } else {
                $like = AdminList::like($q);
                $query->where(function ($inner) use ($like) {
                    $inner->where('users.name', 'like', $like)
                        ->orWhere('users.email', 'like', $like);
                });
            }
        }

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($row) {
                return [
                    'user_id' => (int) $row->user_id,
                    'name' => (string) $row->name,
                    'left' => number_format((float) $row->left_total, 2, '.', ''),
                    'right' => number_format((float) $row->right_total, 2, '.', ''),
                ];
            });
    }
}
