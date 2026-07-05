<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramConfig;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $configs = TelegramConfig::orderByDesc('created_at')->get();

        return response()->json(['data' => $configs]);
    }

    public function store(Request $request, TelegramService $telegramService): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bot_token' => 'required|string|max:255',
            'chat_id' => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        // Verify the bot token is valid
        $botInfo = $telegramService->verifyBotToken($validated['bot_token']);
        if (!$botInfo) {
            return response()->json([
                'message' => 'Invalid Telegram Bot Token. Please check and try again.',
            ], 422);
        }

        $config = TelegramConfig::create($validated);

        return response()->json([
            'data' => $config,
            'bot_info' => $botInfo,
            'message' => 'Telegram config created successfully.',
        ], 201);
    }

    public function show(TelegramConfig $telegramConfig): JsonResponse
    {
        return response()->json([
            'data' => $telegramConfig->makeVisible('bot_token'),
        ]);
    }

    public function update(Request $request, TelegramConfig $telegramConfig): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'bot_token' => 'sometimes|string|max:255',
            'chat_id' => 'sometimes|string|max:100',
            'is_active' => 'boolean',
        ]);

        $telegramConfig->update($validated);

        return response()->json([
            'data' => $telegramConfig->fresh(),
            'message' => 'Telegram config updated successfully.',
        ]);
    }

    public function destroy(TelegramConfig $telegramConfig): JsonResponse
    {
        $telegramConfig->delete();

        return response()->json([
            'message' => 'Telegram config deleted successfully.',
        ]);
    }

    /**
     * Test sending a message to verify the configuration works.
     */
    public function test(TelegramConfig $telegramConfig, TelegramService $telegramService): JsonResponse
    {
        $botInfo = $telegramService->verifyBotToken($telegramConfig->bot_token);

        if (!$botInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Bot token is invalid or expired.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connection verified successfully.',
            'bot_info' => $botInfo,
        ]);
    }
}
