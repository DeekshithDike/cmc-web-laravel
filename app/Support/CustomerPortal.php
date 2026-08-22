<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomerPortal
{
    public const ATTRIBUTE = 'portalCustomer';

    public static function bind(Request $request, User $user): void
    {
        $request->attributes->set(self::ATTRIBUTE, $user);
    }

    public static function isAdminView(?Request $request = null): bool
    {
        $request ??= request();

        return $request->attributes->get(self::ATTRIBUTE) instanceof User;
    }

    public static function member(Request $request): User
    {
        $member = $request->attributes->get(self::ATTRIBUTE);
        if ($member instanceof User) {
            return $member;
        }

        $user = $request->user('customer');
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public static function route(string $name, mixed ...$parameters): string
    {
        if (self::isAdminView()) {
            $member = request()->attributes->get(self::ATTRIBUTE);

            return match ($name) {
                'dashboard' => route('admin.customers.dashboard', $member),
                'tree' => route('admin.customers.tree', $member),
                'tree.show' => route('admin.customers.tree.show', ['customer' => $member, 'id' => $parameters[0]]),
                'withdrawals.history' => route('admin.customers.withdrawals.history', $member),
                'income.history' => route('admin.customers.income.history', $member),
                default => throw new InvalidArgumentException('Unknown customer portal route: '.$name),
            };
        }

        return match ($name) {
            'dashboard' => route('customer.dashboard'),
            'tree' => route('customer.tree'),
            'tree.show' => route('customer.tree.show', $parameters[0]),
            'withdrawals.create' => route('customer.withdrawals.create'),
            'withdrawals.history' => route('customer.withdrawals.history'),
            'income.history' => route('customer.income.history'),
            'password.edit' => route('customer.password.edit'),
            default => throw new InvalidArgumentException('Unknown customer portal route: '.$name),
        };
    }
}
