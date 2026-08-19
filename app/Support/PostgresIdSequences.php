<?php

namespace App\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class PostgresIdSequences
{
    /** @var list<string> */
    private const TABLES = [
        'users',
        'packages',
        'payment_transactions',
        'binary_trees',
        'binary_tree_lefts',
        'binary_tree_rights',
        'referral_incomes',
        'binary_incomes',
        'carry_forwards',
        'payment_details',
        'withdrawals',
        'renewal_histories',
        'wallet_transactions',
        'calculation_jobs',
        'daily_income_runs',
    ];

    /**
     * Restore serial sequences to MAX(id) so the next insert does not reuse an existing PK.
     */
    public static function sync(?string $table = null): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = $table !== null ? [$table] : self::TABLES;
        foreach ($tables as $name) {
            self::syncTable($name);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (UniqueConstraintViolationException $e) {
            if (! self::isPrimaryKeyCollision($e)) {
                throw $e;
            }

            Log::warning('Postgres serial id sequence was behind; synced and retrying insert', [
                'error' => $e->getMessage(),
            ]);
            self::sync();

            return $callback();
        }
    }

    public static function isPrimaryKeyCollision(UniqueConstraintViolationException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '_pkey')
            || str_contains($message, 'PRIMARY KEY')
            || (bool) preg_match('/UNIQUE constraint failed: \w+\.id\b/i', $message);
    }

    private static function syncTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $seqName = $table.'_id_seq';
        $exists = DB::selectOne("SELECT 1 AS ok FROM pg_class WHERE relkind = 'S' AND relname = ?", [$seqName]);
        if (! $exists) {
            return;
        }

        $max = (int) (DB::table($table)->max('id') ?? 0);
        if ($max < 1) {
            return;
        }

        DB::select('SELECT setval(?, ?, true) AS v', [$seqName, $max]);
    }
}
