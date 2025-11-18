<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\AccountController;

Route::prefix('auth')->middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/accounts', [AccountController::class, 'list']);

Route::middleware(['jwt.auth', 'throttle:60,1'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::get('/transaction/{id}', [WalletController::class, 'transaction']);
        Route::get('/statement', [WalletController::class, 'statement']);
    });

    Route::prefix('wallet')->middleware('throttle:10,1')->group(function () {
        Route::post('/deposit', [WalletController::class, 'deposit']);
        Route::post('/withdraw', [WalletController::class, 'withdraw']);
        Route::post('/transfer', [WalletController::class, 'transfer']);
        Route::post('/chargeback', [WalletController::class, 'chargeback']);
        Route::post('/contestar', [WalletController::class, 'contestar']);
    });
});
