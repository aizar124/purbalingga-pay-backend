<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\VoucherController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'ok' => true,
    'app' => 'Purbalingga Pay Backend',
]));

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/sso-login', [AuthController::class, 'ssoLogin']);
    Route::post('/sso-sync', [AuthController::class, 'ssoSync'])->middleware('api.token');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('api.token');
    Route::get('/me', [AuthController::class, 'me'])->middleware('api.token');
});

Route::middleware('api.token')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/cards', [CardController::class, 'index']);
    Route::post('/cards/{card:code}/block', [CardController::class, 'block']);
    Route::post('/cards/{card:code}/unlock', [CardController::class, 'unlock']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::post('/transactions/{id}/simulate-payment', [TransactionController::class, 'simulatePayment'])->whereNumber('id');
    Route::get('/vouchers', [VoucherController::class, 'index']);
    Route::post('/vouchers/{voucher:code}/redeem', [VoucherController::class, 'redeem']);
});
