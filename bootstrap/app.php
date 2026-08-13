<?php

use App\Http\Middleware\EnsureCustomerMembershipActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(function () {
                require __DIR__.'/../routes/admin.php';
                require __DIR__.'/../routes/customer.php';
            });
        },
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('income:daily')->dailyAt((string) config('citymax.income.run_at', '00:05'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecureHeaders::class);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'customer' => EnsureUserIsCustomer::class,
            'membership' => EnsureCustomerMembershipActive::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/payments/*',
            'webhooks/payouts/*',
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return route('customer.login');
        });

        $middleware->redirectUsersTo(function () {
            $user = auth()->user();

            if ($user && $user->isAdmin()) {
                return route('admin.dashboard');
            }

            return route('customer.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
