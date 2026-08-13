<?php

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\CustomerLoginController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::get('/credentials/{token}', [\App\Http\Controllers\Auth\CredentialsController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,64}')
    ->name('credentials.show');

Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.payments.handle');
Route::post('/webhooks/payouts/{provider}', [PaymentWebhookController::class, 'payout'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.payouts.handle');

Route::get('/customer/register', [\App\Http\Controllers\Auth\CustomerRegisterController::class, 'show'])->name('customer.register');
Route::post('/customer/register', [\App\Http\Controllers\Auth\CustomerRegisterController::class, 'store'])
    ->middleware('throttle:register')
    ->name('customer.register.save');
Route::get('/customer/register/special/user', [\App\Http\Controllers\Auth\CustomerRegisterController::class, 'specialShow'])
    ->name('customer.register.special');
Route::post('/customer/register/special/user', [\App\Http\Controllers\Auth\CustomerRegisterController::class, 'specialStore'])
    ->middleware('throttle:register')
    ->name('customer.register.special.save');
Route::get('/customer/payment/success', [\App\Http\Controllers\Auth\PaymentCheckoutController::class, 'success'])
    ->name('customer.payment.success');
Route::get('/customer/payment/cancel', [\App\Http\Controllers\Auth\PaymentCheckoutController::class, 'cancel'])
    ->name('customer.payment.cancel');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminLoginController::class, 'show'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'login'])
        ->middleware('throttle:login')
        ->name('admin.login.submit');

    Route::get('/customer/login', [CustomerLoginController::class, 'show'])->name('customer.login');
    Route::post('/customer/login', [CustomerLoginController::class, 'login'])
        ->middleware('throttle:login')
        ->name('customer.login.submit');
});
