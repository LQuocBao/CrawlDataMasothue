<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\TelegramConfigController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| REST API for the Next.js admin dashboard.
| In production, protect these with Sanctum or similar auth.
|
*/

Route::prefix('v1')->group(function () {
    // Company data (read-only from dashboard)
    Route::get('companies/stats', [CompanyController::class, 'stats']);
    Route::apiResource('companies', CompanyController::class)->only(['index', 'show']);

    // Filter management
    Route::apiResource('filters', FilterController::class);

    // Telegram configuration
    Route::apiResource('telegram-configs', TelegramConfigController::class);
    Route::post('telegram-configs/{telegramConfig}/test', [TelegramConfigController::class, 'test']);
});
