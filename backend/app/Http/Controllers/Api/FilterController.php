<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FilterController extends Controller
{
    public function index(): JsonResponse
    {
        $filters = Filter::with('telegramConfig:id,name,chat_id,is_active')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $filters]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provinces' => 'nullable|array',
            'provinces.*' => 'string|max:100',
            'industry_keywords' => 'nullable|array',
            'industry_keywords.*' => 'string|max:255',
            'industry_codes' => 'nullable|array',
            'industry_codes.*' => 'string|max:10',
            'registration_days_back' => 'nullable|integer|min:1|max:365',
            'require_phone' => 'boolean',
            'is_active' => 'boolean',
            'telegram_config_id' => [
                'nullable',
                Rule::exists('telegram_configs', 'id'),
            ],
        ]);

        $filter = Filter::create($validated);

        return response()->json([
            'data' => $filter->load('telegramConfig:id,name,chat_id'),
            'message' => 'Filter created successfully.',
        ], 201);
    }

    public function show(Filter $filter): JsonResponse
    {
        return response()->json([
            'data' => $filter->load('telegramConfig'),
        ]);
    }

    public function update(Request $request, Filter $filter): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'provinces' => 'nullable|array',
            'provinces.*' => 'string|max:100',
            'industry_keywords' => 'nullable|array',
            'industry_keywords.*' => 'string|max:255',
            'industry_codes' => 'nullable|array',
            'industry_codes.*' => 'string|max:10',
            'registration_days_back' => 'nullable|integer|min:1|max:365',
            'require_phone' => 'boolean',
            'is_active' => 'boolean',
            'telegram_config_id' => [
                'nullable',
                Rule::exists('telegram_configs', 'id'),
            ],
        ]);

        $filter->update($validated);

        return response()->json([
            'data' => $filter->fresh('telegramConfig'),
            'message' => 'Filter updated successfully.',
        ]);
    }

    public function destroy(Filter $filter): JsonResponse
    {
        $filter->delete();

        return response()->json([
            'message' => 'Filter deleted successfully.',
        ]);
    }
}
