<?php

namespace Database\Seeders;

use App\Models\BinaryIncome;
use App\Models\BinaryTreeLeft;
use App\Models\BinaryTreeRight;
use App\Models\CarryForward;
use App\Models\DailyIncomeRun;
use App\Models\PaymentDetail;
use App\Models\ReferralIncome;
use App\Models\User;
use App\Support\PostgresIdSequences;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One-off production repair: IDs 3559 and 3597 were activated, then users.package_id
 * was changed in the database without rewriting tree volume or referral rows.
 *
 * Keeps the latest packages. Does not touch wallets, paid days, or package_id.
 *
 * php artisan db:seed --class=FixManualPackageUpgradeSeeder
 */
class FixManualPackageUpgradeSeeder extends Seeder
{
    private const ANGEL_ID = 3559;

    private const AREEF_ID = 3597;

    private const ROOT_ID = 3558;

    private const BOSS_ID = 3594;

    private const ANGEL_PACKAGE_ID = 9;

    private const AREEF_PACKAGE_ID = 4;

    private const ANGEL_OLD = '500.00';

    private const ANGEL_NEW = '5000.00';

    private const AREEF_OLD = '100.00';

    private const AREEF_NEW = '1000.00';

    private const AREEF_RIGHTS = 28;

    private const ROOT_LEFT_DELTA = '5400.00';

    private const AREEF_RIGHT_DELTA = '900.00';

    private const AREEF_REFERRAL_TOPUP = '900.00';

    public function run(): void
    {
        $state = $this->inspect();

        if ($state['status'] === 'applied') {
            $this->printLines(array_merge(
                ['Already applied. No changes made.'],
                $state['lines'],
            ));

            return;
        }

        if ($state['status'] !== 'pending') {
            $this->fail($state['lines']);
        }

        $result = DB::transaction(function () {
            $untouched = $this->snapshotUntouched();
            $carryBefore = $this->latestCarryMap();
            $earnedOn = $this->nextUnpaidReferralDate();

            $this->applyVolume();
            $this->applyCarry($carryBefore);
            $this->applyReferral($earnedOn);

            $errors = array_merge(
                $this->assertApplied(),
                $this->assertCarryBumped($carryBefore),
                $this->assertUntouched($untouched),
            );

            if ($errors !== []) {
                throw new RuntimeException(implode("\n", $errors));
            }

            return $this->successLines($earnedOn, $carryBefore);
        });

        $this->printLines($result);
    }

    /**
     * @return array{status: string, lines: list<string>}
     */
    private function inspect(): array
    {
        $lines = [];
        $angel = User::query()->with('package:id,amount')->find(self::ANGEL_ID);
        $areef = User::query()->with('package:id,amount')->find(self::AREEF_ID);
        $root = User::query()->find(self::ROOT_ID);
        $boss = User::query()->find(self::BOSS_ID);

        if (! $angel || ! $areef || ! $root || ! $boss) {
            return ['status' => 'error', 'lines' => ['Missing user 3558, 3559, 3594, or 3597.']];
        }

        if ((int) $angel->package_id !== self::ANGEL_PACKAGE_ID || $this->money($angel->package?->amount) !== self::ANGEL_NEW) {
            $lines[] = '3559 must already be Imperial $5000 (package 9). Seeder will not change package_id.';
        }
        if ((int) $areef->package_id !== self::AREEF_PACKAGE_ID || $this->money($areef->package?->amount) !== self::AREEF_NEW) {
            $lines[] = '3597 must already be Platinum $1000 (package 4). Seeder will not change package_id.';
        }

        $angelLeftOld = $this->leftCount(self::ANGEL_ID, self::ANGEL_OLD);
        $angelLeftNew = $this->leftCount(self::ANGEL_ID, self::ANGEL_NEW);
        $angelRight = BinaryTreeRight::query()->where('from_user_id', self::ANGEL_ID)->count();
        $areefLeftOld = $this->leftCount(self::AREEF_ID, self::AREEF_OLD);
        $areefLeftNew = $this->leftCount(self::AREEF_ID, self::AREEF_NEW);
        $areefRightOld = $this->rightCount(self::AREEF_ID, self::AREEF_OLD);
        $areefRightNew = $this->rightCount(self::AREEF_ID, self::AREEF_NEW);
        $angelFromTotal = BinaryTreeLeft::query()->where('from_user_id', self::ANGEL_ID)->count();
        $areefLeftTotal = BinaryTreeLeft::query()->where('from_user_id', self::AREEF_ID)->count();
        $areefRightTotal = BinaryTreeRight::query()->where('from_user_id', self::AREEF_ID)->count();

        $angelRef = ReferralIncome::query()->where('from_user_id', self::ANGEL_ID)->get();
        $areefRef = ReferralIncome::query()->where('from_user_id', self::AREEF_ID)->get();

        if ($angelRight !== 0) {
            $lines[] = '3559 has unexpected right-side volume rows.';
        }
        if ($angelFromTotal !== 1 || $areefLeftTotal !== 1 || $areefRightTotal !== self::AREEF_RIGHTS) {
            $lines[] = 'Volume row counts for 3559/3597 do not match activation (expect 1 left from 3559, 1 left + 28 right from 3597).';
        }

        $angelLeftUser = BinaryTreeLeft::query()->where('from_user_id', self::ANGEL_ID)->value('user_id');
        $areefLeftUser = BinaryTreeLeft::query()->where('from_user_id', self::AREEF_ID)->value('user_id');
        if ((int) $angelLeftUser !== self::ROOT_ID || (int) $areefLeftUser !== self::ROOT_ID) {
            $lines[] = '3559 and 3597 left volume must sit on 3558 only.';
        }

        $rightUsers = BinaryTreeRight::query()
            ->where('from_user_id', self::AREEF_ID)
            ->pluck('user_id');
        if ($rightUsers->count() !== $rightUsers->unique()->count()) {
            $lines[] = '3597 right volume has duplicate ancestor rows.';
        }

        $pending = $angelLeftOld === 1 && $angelLeftNew === 0
            && $areefLeftOld === 1 && $areefLeftNew === 0
            && $areefRightOld === self::AREEF_RIGHTS && $areefRightNew === 0
            && $angelRef->count() === 1 && $this->money($angelRef->first()?->amount) === self::ANGEL_OLD
            && optional($angelRef->first()?->earned_on)?->toDateString() === '2026-08-16'
            && (int) $angelRef->first()?->user_id === self::ROOT_ID
            && $areefRef->count() === 1 && $this->money($areefRef->first()?->amount) === self::AREEF_OLD
            && (int) $areefRef->first()?->user_id === self::BOSS_ID;

        $applied = $angelLeftOld === 0 && $angelLeftNew === 1
            && $areefLeftOld === 0 && $areefLeftNew === 1
            && $areefRightOld === 0 && $areefRightNew === self::AREEF_RIGHTS
            && $angelRef->count() === 1 && $this->money($angelRef->first()?->amount) === self::ANGEL_NEW
            && (int) $angelRef->first()?->user_id === self::ROOT_ID
            && $areefRef->count() === 2
            && $areefRef->contains(fn ($row) => $this->money($row->amount) === self::AREEF_OLD && (int) $row->user_id === self::BOSS_ID)
            && $areefRef->contains(fn ($row) => $this->money($row->amount) === self::AREEF_REFERRAL_TOPUP && (int) $row->user_id === self::BOSS_ID);

        if ($this->latestCarry(self::ROOT_ID) === null) {
            $lines[] = '3558 has no carry_forwards row to receive the extra left volume.';
        }

        if ($lines !== []) {
            return ['status' => 'error', 'lines' => $lines];
        }

        if ($applied) {
            return ['status' => 'applied', 'lines' => $this->currentSummary()];
        }

        if ($pending) {
            return ['status' => 'pending', 'lines' => []];
        }

        return ['status' => 'error', 'lines' => [
            'Data is neither the original mismatch nor the completed fix. Refusing to change anything.',
            "3559 left old/new: {$angelLeftOld}/{$angelLeftNew}",
            "3597 left old/new: {$areefLeftOld}/{$areefLeftNew}",
            "3597 right old/new: {$areefRightOld}/{$areefRightNew}",
            '3559 referral rows: '.$angelRef->count(),
            '3597 referral rows: '.$areefRef->count(),
        ]];
    }

    private function applyVolume(): void
    {
        $angelLeft = BinaryTreeLeft::query()
            ->where('from_user_id', self::ANGEL_ID)
            ->where('amount', self::ANGEL_OLD)
            ->update(['amount' => self::ANGEL_NEW, 'updated_at' => now()]);
        $areefLeft = BinaryTreeLeft::query()
            ->where('from_user_id', self::AREEF_ID)
            ->where('amount', self::AREEF_OLD)
            ->update(['amount' => self::AREEF_NEW, 'updated_at' => now()]);
        $areefRight = BinaryTreeRight::query()
            ->where('from_user_id', self::AREEF_ID)
            ->where('amount', self::AREEF_OLD)
            ->update(['amount' => self::AREEF_NEW, 'updated_at' => now()]);

        if ($angelLeft !== 1 || $areefLeft !== 1 || $areefRight !== self::AREEF_RIGHTS) {
            throw new RuntimeException(
                "Volume update counts were {$angelLeft}/{$areefLeft}/{$areefRight}, expected 1/1/28."
            );
        }
    }

    /**
     * @param  array<int, array{left: string, right: string, as_of: string}>  $carryBefore
     */
    private function applyCarry(array $carryBefore): void
    {
        $root = $this->latestCarry(self::ROOT_ID);
        if (! $root) {
            throw new RuntimeException('3558 latest carry disappeared during the transaction.');
        }

        $root->update([
            'left_carry' => $this->money(bcadd($root->left_carry, self::ROOT_LEFT_DELTA, 2)),
        ]);

        $rightUserIds = BinaryTreeRight::query()
            ->where('from_user_id', self::AREEF_ID)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        foreach ($rightUserIds as $userId) {
            if (! isset($carryBefore[$userId])) {
                continue;
            }
            $row = $this->latestCarry($userId);
            if (! $row) {
                throw new RuntimeException("Latest carry for {$userId} disappeared during the transaction.");
            }
            $row->update([
                'right_carry' => $this->money(bcadd($row->right_carry, self::AREEF_RIGHT_DELTA, 2)),
            ]);
        }
    }

    private function applyReferral(string $earnedOn): void
    {
        $updated = ReferralIncome::query()
            ->where('from_user_id', self::ANGEL_ID)
            ->where('amount', self::ANGEL_OLD)
            ->whereDate('earned_on', '2026-08-16')
            ->update([
                'amount' => self::ANGEL_NEW,
                'earned_on' => $earnedOn,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException("3559 referral update affected {$updated} rows, expected 1.");
        }

        $exists = ReferralIncome::query()
            ->where('from_user_id', self::AREEF_ID)
            ->where('amount', self::AREEF_REFERRAL_TOPUP)
            ->whereDate('earned_on', $earnedOn)
            ->exists();

        if ($exists) {
            throw new RuntimeException('3597 $900 referral top-up already exists while volume was still pending.');
        }

        PostgresIdSequences::sync('referral_incomes');
        ReferralIncome::query()->create([
            'user_id' => self::BOSS_ID,
            'from_user_id' => self::AREEF_ID,
            'amount' => self::AREEF_REFERRAL_TOPUP,
            'earned_on' => $earnedOn,
        ]);
    }

    /**
     * @return list<string>
     */
    private function assertApplied(): array
    {
        $errors = [];
        if ($this->leftCount(self::ANGEL_ID, self::ANGEL_NEW) !== 1) {
            $errors[] = '3559 left volume was not updated to $5000.';
        }
        if ($this->leftCount(self::AREEF_ID, self::AREEF_NEW) !== 1) {
            $errors[] = '3597 left volume was not updated to $1000.';
        }
        if ($this->rightCount(self::AREEF_ID, self::AREEF_NEW) !== self::AREEF_RIGHTS) {
            $errors[] = '3597 right volume was not updated to $1000 on all 28 ancestors.';
        }
        if ($this->leftCount(self::ANGEL_ID, self::ANGEL_OLD) !== 0 || $this->leftCount(self::AREEF_ID, self::AREEF_OLD) !== 0) {
            $errors[] = 'Old $500/$100 volume rows still remain.';
        }
        if ($this->rightCount(self::AREEF_ID, self::AREEF_OLD) !== 0) {
            $errors[] = 'Old $100 right volume rows still remain.';
        }

        $angelRef = ReferralIncome::query()->where('from_user_id', self::ANGEL_ID)->get();
        if ($angelRef->count() !== 1 || $this->money($angelRef->first()?->amount) !== self::ANGEL_NEW) {
            $errors[] = '3559 referral was not updated to $5000.';
        }

        $areefRef = ReferralIncome::query()->where('from_user_id', self::AREEF_ID)->get();
        if ($areefRef->count() !== 2) {
            $errors[] = '3597 should have original $100 referral plus $900 top-up.';
        }

        return $errors;
    }

    /**
     * @param  array<int, array{left: string, right: string, as_of: string}>  $before
     * @return list<string>
     */
    private function assertCarryBumped(array $before): array
    {
        $errors = [];
        $root = $this->latestCarry(self::ROOT_ID);
        $expectedLeft = $this->money(bcadd($before[self::ROOT_ID]['left'] ?? '0.00', self::ROOT_LEFT_DELTA, 2));
        if (! $root || $this->money($root->left_carry) !== $expectedLeft) {
            $errors[] = '3558 latest left carry was not increased by $5400.';
        }
        if ($root && $this->money($root->right_carry) !== ($before[self::ROOT_ID]['right'] ?? '0.00')) {
            $errors[] = '3558 right carry changed; it must stay the same.';
        }

        $rightUserIds = BinaryTreeRight::query()
            ->where('from_user_id', self::AREEF_ID)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        foreach ($rightUserIds as $userId) {
            if (! isset($before[$userId])) {
                continue;
            }
            $row = $this->latestCarry($userId);
            $expectedRight = $this->money(bcadd($before[$userId]['right'], self::AREEF_RIGHT_DELTA, 2));
            if (! $row || $this->money($row->right_carry) !== $expectedRight) {
                $errors[] = "{$userId} latest right carry was not increased by $900.";
            }
            if ($row && $this->money($row->left_carry) !== $before[$userId]['left']) {
                $errors[] = "{$userId} left carry changed; it must stay the same.";
            }
        }

        foreach ($before as $userId => $old) {
            if ($userId === self::ROOT_ID || $rightUserIds->contains($userId)) {
                continue;
            }
            $row = $this->latestCarry($userId);
            if (! $row) {
                $errors[] = "{$userId} latest carry disappeared.";
                continue;
            }
            if ($this->money($row->left_carry) !== $old['left'] || $this->money($row->right_carry) !== $old['right']) {
                $errors[] = "{$userId} carry changed but this ID is not in the 3559/3597 upline.";
            }
        }

        return $errors;
    }

    /**
     * @return array{wallets: array<int, string>, packages: array<int, int|null>, payments: int, wallets_tx: int, binary: int, runs: string, older_carry: string}
     */
    private function snapshotUntouched(): array
    {
        return [
            'wallets' => User::query()->orderBy('id')->pluck('wallet_balance', 'id')->map(fn ($v) => $this->money($v))->all(),
            'packages' => User::query()->orderBy('id')->pluck('package_id', 'id')->map(fn ($v) => $v === null ? null : (int) $v)->all(),
            'payments' => PaymentDetail::query()->count(),
            'wallets_tx' => DB::table('wallet_transactions')->count(),
            'binary' => BinaryIncome::query()->count(),
            'runs' => $this->runsFingerprint(),
            'older_carry' => $this->olderCarryFingerprint(),
        ];
    }

    /**
     * @param  array{wallets: array<int, string>, packages: array<int, int|null>, payments: int, wallets_tx: int, binary: int, runs: string, older_carry: string}  $before
     * @return list<string>
     */
    private function assertUntouched(array $before): array
    {
        $errors = [];
        $wallets = User::query()->orderBy('id')->pluck('wallet_balance', 'id')->map(fn ($v) => $this->money($v))->all();
        if ($wallets !== $before['wallets']) {
            $errors[] = 'A wallet_balance changed. Seeder must not pay anyone.';
        }
        $packages = User::query()->orderBy('id')->pluck('package_id', 'id')->map(fn ($v) => $v === null ? null : (int) $v)->all();
        if ($packages !== $before['packages']) {
            $errors[] = 'A package_id changed. Latest packages must stay as they are.';
        }
        if (PaymentDetail::query()->count() !== $before['payments']) {
            $errors[] = 'payment_details changed.';
        }
        if ((int) DB::table('wallet_transactions')->count() !== $before['wallets_tx']) {
            $errors[] = 'wallet_transactions changed.';
        }
        if (BinaryIncome::query()->count() !== $before['binary']) {
            $errors[] = 'binary_incomes changed.';
        }
        if ($this->runsFingerprint() !== $before['runs']) {
            $errors[] = 'daily_income_runs changed.';
        }
        if ($this->olderCarryFingerprint() !== $before['older_carry']) {
            $errors[] = 'A historical carry_forwards row (not the latest per user) changed.';
        }

        return $errors;
    }

    private function nextUnpaidReferralDate(): string
    {
        $date = Carbon::parse('2026-08-20')->startOfDay();
        for ($i = 0; $i < 30; $i++) {
            $asOf = $date->toDateString();
            $blocked = DailyIncomeRun::query()
                ->whereDate('as_of', $asOf)
                ->whereIn('status', [DailyIncomeRun::STATUS_COMPLETED, DailyIncomeRun::STATUS_RUNNING])
                ->exists();
            if (! $blocked) {
                return $asOf;
            }
            $date->addDay();
        }

        throw new RuntimeException('No unpaid day found to attach the missing referral.');
    }

    private function latestCarry(int $userId): ?CarryForward
    {
        return CarryForward::query()
            ->where('user_id', $userId)
            ->orderByDesc('as_of')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<int, array{left: string, right: string, as_of: string}>
     */
    private function latestCarryMap(): array
    {
        $map = [];
        foreach (CarryForward::query()->orderBy('user_id')->orderByDesc('as_of')->orderByDesc('id')->get() as $row) {
            $id = (int) $row->user_id;
            if (isset($map[$id])) {
                continue;
            }
            $map[$id] = [
                'left' => $this->money($row->left_carry),
                'right' => $this->money($row->right_carry),
                'as_of' => (string) $row->as_of?->toDateString(),
            ];
        }

        return $map;
    }

    private function olderCarryFingerprint(): string
    {
        $latest = $this->latestCarryMap();
        $rows = CarryForward::query()->orderBy('id')->get()
            ->filter(function (CarryForward $row) use ($latest) {
                $asOf = (string) $row->as_of?->toDateString();

                return ($latest[(int) $row->user_id]['as_of'] ?? null) !== $asOf;
            })
            ->map(fn (CarryForward $row) => implode('|', [
                $row->id,
                $row->user_id,
                $this->money($row->left_carry),
                $this->money($row->right_carry),
                $row->as_of?->toDateString(),
            ]))
            ->values()
            ->all();

        return implode("\n", $rows);
    }

    private function runsFingerprint(): string
    {
        return DailyIncomeRun::query()
            ->orderBy('as_of')
            ->get(['id', 'as_of', 'status', 'processed', 'total_paid'])
            ->map(fn ($row) => implode('|', [
                $row->id,
                $row->as_of?->toDateString(),
                $row->status,
                $row->processed,
                $this->money($row->total_paid),
            ]))
            ->implode("\n");
    }

    /**
     * @param  array<int, array{left: string, right: string, as_of: string}>  $carryBefore
     * @return list<string>
     */
    private function successLines(string $earnedOn, array $carryBefore): array
    {
        $rightWithCarry = 0;
        $rightUserIds = BinaryTreeRight::query()
            ->where('from_user_id', self::AREEF_ID)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique();
        foreach ($rightUserIds as $userId) {
            if (isset($carryBefore[$userId])) {
                $rightWithCarry++;
            }
        }

        return [
            'Applied successfully. Transaction committed.',
            'Packages unchanged: 3559=$5000, 3597=$1000.',
            'Volume: 3559 left $500→$5000 (1 row), 3597 $100→$1000 (1 left + 28 right).',
            'Carry: 3558 left +$5400; '.$rightWithCarry.' right-side uplines with carry +$900. Power IDs without carry were skipped.',
            "Referral: 3558 will receive 10% of $5000 and 3594 will receive 10% of $900 on {$earnedOn}.",
            'Wallets, payment_details, and 17–19 Aug runs were not changed.',
            'Do not re-run 17/18/19 income. Let the normal job pay '.$earnedOn.'.',
        ];
    }

    /**
     * @return list<string>
     */
    private function currentSummary(): array
    {
        $angelRef = ReferralIncome::query()->where('from_user_id', self::ANGEL_ID)->first();
        $topup = ReferralIncome::query()
            ->where('from_user_id', self::AREEF_ID)
            ->where('amount', self::AREEF_REFERRAL_TOPUP)
            ->first();

        return [
            '3559 left $5000 rows: '.$this->leftCount(self::ANGEL_ID, self::ANGEL_NEW),
            '3597 left $1000 rows: '.$this->leftCount(self::AREEF_ID, self::AREEF_NEW),
            '3597 right $1000 rows: '.$this->rightCount(self::AREEF_ID, self::AREEF_NEW),
            '3559 referral: '.$this->money($angelRef?->amount).' on '.(string) $angelRef?->earned_on?->toDateString(),
            '3597 top-up referral: '.$this->money($topup?->amount).' on '.(string) $topup?->earned_on?->toDateString(),
        ];
    }

    private function leftCount(int $fromUserId, string $amount): int
    {
        return BinaryTreeLeft::query()->where('from_user_id', $fromUserId)->where('amount', $amount)->count();
    }

    private function rightCount(int $fromUserId, string $amount): int
    {
        return BinaryTreeRight::query()->where('from_user_id', $fromUserId)->where('amount', $amount)->count();
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  list<string>  $lines
     */
    private function printLines(array $lines): void
    {
        foreach ($lines as $line) {
            $this->command?->info($line);
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function fail(array $lines): never
    {
        $message = implode("\n", $lines);
        foreach ($lines as $line) {
            $this->command?->error($line);
        }

        throw new RuntimeException($message);
    }
}
