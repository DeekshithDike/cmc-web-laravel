<?php

use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IncomeController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\PowerIdController;
use App\Http\Controllers\Admin\RenewalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\Auth\AdminLoginController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:admin', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/export', [ExportController::class, 'activeUsers'])->name('users.export');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])
        ->whereNumber('user')
        ->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->whereNumber('user')
        ->name('users.update');
    Route::put('/users/{user}/password', [UserController::class, 'updatePassword'])
        ->whereNumber('user')
        ->name('users.password');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/start', [PaymentController::class, 'start'])->name('payments.start');
    Route::post('/payments/{payment}/confirm', [PaymentController::class, 'confirm'])
        ->whereNumber('payment')
        ->name('payments.confirm');

    Route::get('/income/daily', [IncomeController::class, 'daily'])->name('income.daily');
    Route::post('/income/daily/run', [IncomeController::class, 'run'])
        ->middleware('throttle:income-run')
        ->name('income.daily.run');

    Route::get('/verification', [VerificationController::class, 'index'])->name('verification.index');

    Route::get('/power-ids', [PowerIdController::class, 'index'])->name('power.index');
    Route::post('/power-ids', [PowerIdController::class, 'store'])->name('power.store');
    Route::get('/power-ids/activate', [PowerIdController::class, 'activateForm'])->name('power.activate');
    Route::post('/power-ids/activate', [PowerIdController::class, 'activate'])->name('power.activate.save');

    Route::get('/withdrawals/{status?}', [WithdrawalController::class, 'index'])
        ->whereIn('status', ['pending', 'processing', 'completed', 'declined'])
        ->name('withdrawals.index');
    Route::post('/withdrawals/{withdrawal}/complete', [WithdrawalController::class, 'complete'])
        ->whereNumber('withdrawal')
        ->name('withdrawals.complete');
    Route::post('/withdrawals/{withdrawal}/decline', [WithdrawalController::class, 'decline'])
        ->whereNumber('withdrawal')
        ->name('withdrawals.decline');
    Route::post('/withdrawals/sync-processing', [WithdrawalController::class, 'syncProcessing'])
        ->name('withdrawals.sync-processing');
    Route::get('/withdrawals-export/completed', [ExportController::class, 'completedWithdrawals'])
        ->name('withdrawals.export.completed');

    Route::get('/business/all', [BusinessController::class, 'allUsers'])->name('business.all');
    Route::get('/business/offer', [BusinessController::class, 'offer'])->name('business.offer');

    Route::get('/renewals/active', [RenewalController::class, 'active'])->name('renewals.active');
    Route::post('/renewals/{user}/renew', [RenewalController::class, 'renew'])
        ->whereNumber('user')
        ->name('renewals.renew');
    Route::get('/renewals/renewed', [RenewalController::class, 'renewed'])->name('renewals.renewed');
    Route::get('/renewals/expired', [RenewalController::class, 'expired'])->name('renewals.expired');

    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');
});
