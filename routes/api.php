<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FarmController;
use App\Http\Controllers\Api\GoatController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]);
});

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user/password', [AuthController::class, 'password']);

    Route::get('/farms', [FarmController::class, 'index']);
    Route::post('/farms', [FarmController::class, 'store']);
    Route::get('/farms/{uuid}/goats', [FarmController::class, 'goats']);
    Route::get('/farms/{uuid}', [FarmController::class, 'show']);
    Route::put('/farms/{uuid}', [FarmController::class, 'update']);
    Route::delete('/farms/{uuid}', [FarmController::class, 'destroy']);

    Route::get('/goats', [GoatController::class, 'index']);
    Route::post('/goats', [GoatController::class, 'store']);
    Route::get('/goats/{uuid}', [GoatController::class, 'show']);
    Route::put('/goats/{uuid}', [GoatController::class, 'update']);
    Route::delete('/goats/{uuid}', [GoatController::class, 'destroy']);

    Route::post('/sync', [SyncController::class, 'push']);
    Route::get('/sync/pull', [SyncController::class, 'pull']);
    Route::get('/sync/summary', [SyncController::class, 'summary']);

    Route::get('/reports/summary', [ReportController::class, 'summary']);
    Route::get('/reports/farms', [ReportController::class, 'farms']);
    Route::get('/reports/goats', [ReportController::class, 'goats']);

    Route::middleware('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });
});
