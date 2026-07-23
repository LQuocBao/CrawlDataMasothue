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
| Routes nhận dữ liệu từ Extension được bảo vệ bằng middleware extension.secret.
|
*/

Route::prefix('v1')->group(function () {

    // ---------------------------------------------------------------
    // ROUTES DÀNH CHO DASHBOARD (đọc - không cần auth)
    // ---------------------------------------------------------------
    Route::get('companies/stats', [CompanyController::class, 'stats']);
    Route::get('companies', [CompanyController::class, 'index']);
    Route::get('companies/{company}', [CompanyController::class, 'show']);

    Route::get('filters', [FilterController::class, 'index']);
    Route::get('telegram-configs', [TelegramConfigController::class, 'index']);

    Route::get('sheets', [\App\Http\Controllers\Api\GoogleSheetController::class, 'index']);
    Route::get('settings', [\App\Http\Controllers\Api\SettingsController::class, 'index']);

    // ---------------------------------------------------------------
    // ROUTES NHẬN DỮ LIỆU TỪ EXTENSION (ghi - bắt buộc có secret)
    // ---------------------------------------------------------------
    Route::middleware('extension.secret')->group(function () {
        Route::post('companies', [CompanyController::class, 'store']);
    });

    // ---------------------------------------------------------------
    // ROUTES QUẢN TRỊ HỆ THỐNG (ghi - bắt buộc có secret)
    // ---------------------------------------------------------------
    Route::middleware('extension.secret')->group(function () {
        Route::post('filters', [FilterController::class, 'store']);
        Route::put('filters/{filter}', [FilterController::class, 'update']);
        Route::delete('filters/{filter}', [FilterController::class, 'destroy']);

        Route::post('telegram-configs', [TelegramConfigController::class, 'store']);
        Route::put('telegram-configs/{telegramConfig}', [TelegramConfigController::class, 'update']);
        Route::delete('telegram-configs/{telegramConfig}', [TelegramConfigController::class, 'destroy']);
        Route::post('telegram-configs/{telegramConfig}/test', [TelegramConfigController::class, 'test']);

        Route::put('settings', [\App\Http\Controllers\Api\SettingsController::class, 'update']);
        Route::post('settings/test-sheet', [\App\Http\Controllers\Api\SettingsController::class, 'testSheet']);
    });
});
