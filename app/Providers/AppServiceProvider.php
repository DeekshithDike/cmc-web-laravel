<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\PayoutGatewayInterface;
use App\Services\Calc\CalcClientInterface;
use App\Services\Calc\HttpCalcClient;
use App\Services\Payments\NowPayments\NowPaymentsClient;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payouts\PayoutGatewayManager;
use App\Support\CustomerPortal;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('Support/helpers.php');

        $this->app->singleton(CalcClientInterface::class, HttpCalcClient::class);
        $this->app->singleton(NowPaymentsClient::class);
        $this->app->singleton(PaymentGatewayManager::class);
        $this->app->singleton(PayoutGatewayManager::class);

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayManager::class)->driver();
        });

        $this->app->bind(PayoutGatewayInterface::class, function ($app) {
            return $app->make(PayoutGatewayManager::class)->driver();
        });
    }

    public function boot(): void
    {
        Paginator::useBootstrapFour();

        View::composer(['layouts.customer', 'customer.*'], function ($view) {
            $request = request();
            $view->with([
                'isAdminView' => CustomerPortal::isAdminView($request),
                'portalMember' => $request->attributes->get(CustomerPortal::ATTRIBUTE) ?? $request->user('customer'),
            ]);
        });

        RateLimiter::for('login', function (Request $request) {
            $identity = strtolower((string) $request->input('email', $request->input('login_id', 'guest')));

            return Limit::perMinute(10)->by($identity.'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('income-run', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
