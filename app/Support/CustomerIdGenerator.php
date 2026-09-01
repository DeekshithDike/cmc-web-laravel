<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class CustomerIdGenerator
{
    public const MIN = 1000;

    public const MAX = 999999;

    public const MAX_ATTEMPTS = 20;

    /** @var list<int> */
    private static array $forced = [];

    /**
     * @param  list<int>  $ids
     */
    public static function forceNext(array $ids): void
    {
        self::$forced = array_values($ids);
    }

    public static function allocate(): int
    {
        if (self::$forced !== []) {
            return (int) array_shift(self::$forced);
        }

        for ($i = 0; $i < 50; $i++) {
            $id = random_int(self::MIN, self::MAX);
            if (! User::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        throw new RuntimeException('Could not assign a unique Customer ID. Please try again.');
    }

    /**
     * @template T
     *
     * @param  callable(int): T  $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        $last = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $callback(self::allocate());
            } catch (UniqueConstraintViolationException $e) {
                if (! self::isUsersIdCollision($e)) {
                    throw $e;
                }

                $last = $e;
                Log::warning('Customer ID already exists; retrying with a new ID', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new RuntimeException('Could not assign a unique Customer ID. Please try again.', 0, $last);
    }

    public static function isUsersIdCollision(UniqueConstraintViolationException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'users_pkey')
            || (bool) preg_match('/UNIQUE constraint failed: users\.id\b/i', $message)
            || (bool) preg_match("/Duplicate entry .+ for key ['\"]users\.PRIMARY['\"]/i", $message);
    }

    public static function isInRange(int $id): bool
    {
        return $id >= self::MIN && $id <= self::MAX;
    }
}
