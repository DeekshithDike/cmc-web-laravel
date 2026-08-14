<?php

use App\Http\Middleware\EnsureCustomerMembershipActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsCustomer;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\UseRequestRootUrl;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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
        $schedule->command('income:daily')
            ->timezone((string) config('citymax.income.timezone', 'Asia/Kuala_Lumpur'))
            ->dailyAt((string) config('citymax.income.run_at', '00:00'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecureHeaders::class);
        $middleware->web(prepend: [
            UseRequestRootUrl::class,
        ]);

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

        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            return route('customer.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Symfony\Component\HttpFoundation\Response $response, \Throwable $e, Request $request) {
            $onRegister = $request->is('customer/register', 'customer/register/*');
            $onCustomerForm = $onRegister || $request->is('customer/login', 'admin/login');
            $wantsJson = $request->expectsJson() || $request->ajax();

            if ($onRegister && $wantsJson) {
                if ($e instanceof ValidationException) {
                    return response()->json([
                        'ok' => false,
                        'error' => $e->validator->errors()->first() ?: 'Please check the form and try again.',
                        'errors' => $e->errors(),
                    ], 422);
                }

                if ($response->getStatusCode() === 419 || $e instanceof TokenMismatchException) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Your session expired. Refresh the page and try again.',
                    ], 419);
                }

                if ($response->getStatusCode() === 429 || $e instanceof TooManyRequestsHttpException) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Too many attempts. Please wait a minute and try again.',
                    ], 429);
                }

                if ($response->getStatusCode() >= 500) {
                    $ref = 'REG-'.strtoupper(Str::random(6));
                    Log::error('Customer register request failed', [
                        'ref' => $ref,
                        'path' => $request->path(),
                        'type' => $e::class,
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json([
                        'ok' => false,
                        'error' => 'We could not start payment. Please try again. If it continues, contact support with code '.$ref.'.',
                    ], 500);
                }

                return $response;
            }

            if (! $onCustomerForm || $wantsJson) {
                return $response;
            }

            if ($response->getStatusCode() === 419 || $e instanceof TokenMismatchException) {
                Log::warning('Customer form session expired', [
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);

                return back()->withInput()->with('error', 'Your session expired. Refresh the page and try again.');
            }

            if ($response->getStatusCode() === 429 || $e instanceof TooManyRequestsHttpException) {
                Log::warning('Customer form rate limited', [
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);

                return back()->withInput()->with('error', 'Too many attempts. Please wait a minute and try again.');
            }

            return $response;
        });
    })->create();
