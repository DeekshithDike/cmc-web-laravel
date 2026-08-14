<?php

use App\Http\Controllers\Auth\CustomerLoginController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\IncomeController;
use App\Http\Controllers\Customer\PasswordController;
use App\Http\Controllers\Customer\TreeController;
use App\Http\Controllers\Customer\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:customer', 'customer', 'membership'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/tree', TreeController::class)->name('tree');
    Route::get('/tree/{id}', [TreeController::class, 'show'])->whereNumber('id')->name('tree.show');

    Route::get('/withdrawals/create', [WithdrawalController::class, 'create'])->name('withdrawals.create');
    Route::post('/withdrawals', [WithdrawalController::class, 'store'])->name('withdrawals.store');
    Route::get('/withdrawals/history', [WithdrawalController::class, 'history'])->name('withdrawals.history');

    Route::get('/income/history', [IncomeController::class, 'history'])->name('income.history');

    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('/logout', [CustomerLoginController::class, 'logout'])->name('logout');
});
