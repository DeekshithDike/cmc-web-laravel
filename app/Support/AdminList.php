<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AdminList
{
    public const PER_PAGE = 25;

    public static function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', self::PER_PAGE);

        return max(10, min(100, $perPage ?: self::PER_PAGE));
    }

    public static function search(Request $request, string $key = 'q'): string
    {
        return trim((string) $request->query($key, ''));
    }

    public static function like(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }

    public static function isNumericId(string $q): bool
    {
        return $q !== '' && ctype_digit($q);
    }

    /**
     * Numeric queries match the id column exactly.
     * Text queries LIKE across the given columns.
     *
     * @param  list<string>  $columns
     */
    public static function applySearch(Builder $query, string $q, array $columns, string $idColumn = 'id'): Builder
    {
        if ($q === '') {
            return $query;
        }

        if (self::isNumericId($q)) {
            return $query->where($idColumn, (int) $q);
        }

        $like = self::like($q);

        return $query->where(function (Builder $inner) use ($like, $columns) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $inner->where($column, 'like', $like);
                } else {
                    $inner->orWhere($column, 'like', $like);
                }
            }
        });
    }
}
