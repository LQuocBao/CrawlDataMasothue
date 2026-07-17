<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Controller quản lý cấu hình hệ thống từ Dashboard.
 * Cho phép đổi Google Sheet URL, bật/tắt tính năng mà không cần deploy lại.
 */
class SettingsController extends Controller
{
    /**
     * GET /api/v1/settings
     * Trả về tất cả settings.
     */
    public function index(): JsonResponse
    {
        $settings = AppSetting::orderBy('key')->get();

        return response()->json(['data' => $settings]);
    }

    /**
     * PUT /api/v1/settings
     * Cập nhật nhiều settings cùng lúc.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.google_sheet_webhook_url' => 'sometimes|nullable|url|max:500',
            'settings.google_sheet_enabled' => 'sometimes|in:0,1',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            AppSetting::setValue($key, $value);
        }

        return response()->json([
            'data' => AppSetting::orderBy('key')->get(),
            'message' => 'Cập nhật cài đặt thành công.',
        ]);
    }

    /**
     * POST /api/v1/settings/test-sheet
     * Test kết nối tới Google Sheet webhook.
     */
    public function testSheet(Request $request): JsonResponse
    {
        $url = $request->input('url') ?: AppSetting::getValue(AppSetting::KEY_GOOGLE_SHEET_WEBHOOK);

        if (!$url) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa cấu hình Google Sheet URL.',
            ], 422);
        }

        try {
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kết nối Google Sheet thành công!',
                    'response' => $response->json() ?? $response->body(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Google Sheet trả về lỗi: HTTP {$response->status()}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('SettingsController: Sheet test failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => "Không thể kết nối: {$e->getMessage()}",
            ], 500);
        }
    }
}
