<?php

namespace Tests\Unit;

use App\Support\CustomerIdGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class CustomerIdGeneratorTest extends TestCase
{
    protected function tearDown(): void
    {
        CustomerIdGenerator::forceNext([]);
        parent::tearDown();
    }

    public function test_users_primary_key_collision_is_detected(): void
    {
        $this->assertTrue(CustomerIdGenerator::isUsersIdCollision(
            $this->uniqueException('duplicate key value violates unique constraint "users_pkey"')
        ));
        $this->assertTrue(CustomerIdGenerator::isUsersIdCollision(
            $this->uniqueException('UNIQUE constraint failed: users.id')
        ));
        $this->assertFalse(CustomerIdGenerator::isUsersIdCollision(
            $this->uniqueException('duplicate key value violates unique constraint "users_email_unique"')
        ));
        $this->assertFalse(CustomerIdGenerator::isUsersIdCollision(
            $this->uniqueException('duplicate key value violates unique constraint "payment_transactions_pkey"')
        ));
        $this->assertFalse(CustomerIdGenerator::isUsersIdCollision(
            $this->uniqueException('UNIQUE constraint failed: binary_trees.users_id')
        ));
    }

    public function test_run_retries_on_users_id_collision_with_a_new_id(): void
    {
        CustomerIdGenerator::forceNext([3558, 424242]);

        $seen = [];
        $result = CustomerIdGenerator::run(function (int $id) use (&$seen) {
            $seen[] = $id;
            if ($id === 3558) {
                throw $this->uniqueException('UNIQUE constraint failed: users.id');
            }

            return $id;
        });

        $this->assertSame(424242, $result);
        $this->assertSame([3558, 424242], $seen);
    }

    public function test_run_does_not_retry_email_unique_collisions(): void
    {
        CustomerIdGenerator::forceNext([424242]);

        $this->expectException(UniqueConstraintViolationException::class);

        CustomerIdGenerator::run(function () {
            throw $this->uniqueException('duplicate key value violates unique constraint "users_email_unique"');
        });
    }

    public function test_run_stops_after_max_attempts(): void
    {
        CustomerIdGenerator::forceNext(array_fill(0, CustomerIdGenerator::MAX_ATTEMPTS, 3558));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not assign a unique Customer ID');

        CustomerIdGenerator::run(function () {
            throw $this->uniqueException('UNIQUE constraint failed: users.id');
        });
    }

    public function test_range_covers_four_to_six_digits(): void
    {
        $this->assertTrue(CustomerIdGenerator::isInRange(1000));
        $this->assertTrue(CustomerIdGenerator::isInRange(999999));
        $this->assertFalse(CustomerIdGenerator::isInRange(999));
        $this->assertFalse(CustomerIdGenerator::isInRange(1000000));
    }

    private function uniqueException(string $message): UniqueConstraintViolationException
    {
        return new UniqueConstraintViolationException(
            'sqlite',
            'insert into "users" ("id") values (?)',
            [3558],
            new PDOException($message)
        );
    }
}
