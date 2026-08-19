<?php

namespace Tests\Unit;

use App\Support\PostgresIdSequences;
use Illuminate\Database\UniqueConstraintViolationException;
use PDOException;
use Tests\TestCase;

class PostgresIdSequencesTest extends TestCase
{
    public function test_primary_key_collision_is_detected(): void
    {
        $this->assertTrue(PostgresIdSequences::isPrimaryKeyCollision(
            $this->uniqueException('duplicate key value violates unique constraint "payment_transactions_pkey"')
        ));
        $this->assertFalse(PostgresIdSequences::isPrimaryKeyCollision(
            $this->uniqueException('duplicate key value violates unique constraint "users_email_unique"')
        ));
    }

    public function test_run_retries_once_after_primary_key_collision(): void
    {
        $calls = 0;
        $result = PostgresIdSequences::run(function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw $this->uniqueException('duplicate key value violates unique constraint "payment_transactions_pkey"');
            }

            return 'ok';
        });

        $this->assertSame('ok', $result);
        $this->assertSame(2, $calls);
    }

    public function test_run_does_not_retry_email_unique_collisions(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        PostgresIdSequences::run(function () {
            throw $this->uniqueException('duplicate key value violates unique constraint "users_email_unique"');
        });
    }

    public function test_sync_is_a_noop_on_sqlite(): void
    {
        PostgresIdSequences::sync();
        PostgresIdSequences::sync('payment_transactions');

        $this->assertTrue(true);
    }

    private function uniqueException(string $message): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(
            'pgsql',
            'insert into "payment_transactions" ("amount") values (?)',
            ['100.00'],
            new PDOException($message)
        );
    }
}
