<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RechargeController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/{order}/retry', [OrderController::class, 'retry']);

        Route::get('/recharges', [RechargeController::class, 'index']);
        Route::post('/recharges', [RechargeController::class, 'store']);
        Route::get('/recharges/{rechargeTransaction}', [RechargeController::class, 'show']);
    });
});
