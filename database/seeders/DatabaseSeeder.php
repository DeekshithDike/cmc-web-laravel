<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BinaryTree;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Application tables wiped on every seed. Schema/migrations are left intact.
     *
     * @var list<string>
     */
    private const TABLES = [
        'daily_income_runs',
        'wallet_transactions',
        'calculation_jobs',
        'payment_transactions',
        'renewal_histories',
        'withdrawals',
        'payment_details',
        'carry_forwards',
        'binary_incomes',
        'referral_incomes',
        'binary_tree_rights',
        'binary_tree_lefts',
        'binary_trees',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'password_reset_tokens',
        'users',
        'packages',
    ];

    public function run(): void
    {
        $this->requireSeedPasswords();
        $this->truncateAll();
        $this->seedPackages();
        $this->seedAdminAndParent();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function requireSeedPasswords(): array
    {
        $seed = config('citymax.seed');
        $adminPassword = (string) ($seed['admin_password'] ?? '');
        $customerPassword = (string) ($seed['customer_password'] ?? '');

        if ($adminPassword === '' || $customerPassword === '') {
            throw new RuntimeException('Set SEED_ADMIN_PASSWORD and SEED_CUSTOMER_PASSWORD in .env before seeding.');
        }

        return [$adminPassword, $customerPassword];
    }

    private function truncateAll(): void
    {
        $tables = array_values(array_filter(
            self::TABLES,
            static fn (string $table): bool => Schema::hasTable($table)
        ));

        if ($tables === []) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            $quoted = implode(', ', array_map(
                static fn (string $table): string => '"'.$table.'"',
                $tables
            ));
            DB::statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");

            return;
        }

        Schema::disableForeignKeyConstraints();

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();
    }

    private function seedPackages(): void
    {
        foreach (config('citymax.packages', []) as $plan) {
            Package::query()->create([
                'name' => $plan['name'],
                'amount' => $plan['amount'],
                'roi_percent' => $plan['roi_percent'],
                'is_active' => true,
                'sort_order' => $plan['sort_order'],
            ]);
        }
    }

    private function seedAdminAndParent(): void
    {
        $seed = config('citymax.seed');
        $adminId = (int) ($seed['admin_id'] ?? 1);
        $customerId = (int) ($seed['customer_id'] ?? 3558);
        [$adminPassword, $customerPassword] = $this->requireSeedPasswords();

        $starter = Package::query()->where('amount', '100.00')->first();

        User::query()->forceCreate([
            'id' => $adminId,
            'name' => $seed['admin_name'],
            'email' => $seed['admin_email'],
            'password' => $adminPassword,
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'wallet_balance' => '0.00',
        ]);

        $root = User::query()->forceCreate([
            'id' => $customerId,
            'name' => $seed['customer_name'],
            'email' => $seed['customer_email'],
            'password' => $customerPassword,
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
            'is_active' => true,
            'payment_status' => true,
            'is_power_id' => false,
            'package_id' => $starter?->id,
            'expiry_date' => now()->addWeekdays((int) config('citymax.membership.weekdays', 150))->toDateString(),
            'phone' => $seed['customer_phone'],
            'country' => $seed['customer_country'],
            'wallet_balance' => '0.00',
        ]);

        BinaryTree::query()->create([
            'users_id' => $root->id,
            'parent_id' => null,
            'position' => null,
        ]);

        $this->bumpUsersSequence($customerId);
    }

    private function bumpUsersSequence(int $lastId): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), ?, true)", [$lastId]);

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE users AUTO_INCREMENT = '.($lastId + 1));

            return;
        }

        if (Schema::hasTable('sqlite_sequence')) {
            DB::table('sqlite_sequence')->where('name', 'users')->delete();
            DB::table('sqlite_sequence')->insert(['name' => 'users', 'seq' => $lastId]);
        }
    }
}
