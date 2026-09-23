<?php

use App\Http\Controllers\Internal\ServerJobController;
use Illuminate\Support\Facades\Route;

$pattern = '/^[A-Za-z0-9]{32,80}$/';
$income = (string) config('server_jobs.income_path');
$payment = (string) config('server_jobs.payment_path');

if ($income !== '' && preg_match($pattern, $income)) {
    Route::any('/'.$income, [ServerJobController::class, 'income'])
        ->middleware(['server-job', 'throttle:server-job'])
        ->name('server-job.income');
}

if ($payment !== '' && $payment !== $income && preg_match($pattern, $payment)) {
    Route::any('/'.$payment, [ServerJobController::class, 'payments'])
        ->middleware(['server-job', 'throttle:server-job'])
        ->name('server-job.payments');
}
