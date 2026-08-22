<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\CustomerPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BindAdminPortalCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $param = $request->route('customer');
        $id = $param instanceof User ? $param->getKey() : $param;

        $user = User::query()
            ->with(['package:id,name,amount'])
            ->findOrFail($id);

        abort_unless($user->isCustomer(), 404);

        CustomerPortal::bind($request, $user);

        return $next($request);
    }
}
