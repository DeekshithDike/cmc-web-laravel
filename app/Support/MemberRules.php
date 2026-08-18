<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class MemberRules
{
    /** @return list<mixed> */
    public static function name(): array
    {
        return ['required', 'string', 'max:60'];
    }

    /** @return list<mixed> */
    public static function email(?int $ignoreUserId = null): array
    {
        $unique = Rule::unique('users', 'email');
        if ($ignoreUserId !== null) {
            $unique = $unique->ignore($ignoreUserId);
        }

        return ['required', 'email', 'max:100', $unique];
    }

    public static function assignedPassword(): Password
    {
        return Password::min(8)->mixedCase()->numbers()->symbols();
    }
}
