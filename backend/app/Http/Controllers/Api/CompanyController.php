<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Company::query()->orderByDesc('scraped_at');

        // Optional filters
        if ($request->filled('province')) {
            $query->where('province', 'like', "%{$request->province}%");
        }

        if ($request->filled('notification_sent')) {
            $query->where('notification_sent', $request->boolean('notification_sent'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mst', 'like', "%{$search}%")
                    ->orWhere('representative', 'like', "%{$search}%");
            });
        }

        $companies = $query->paginate($request->integer('per_page', 25));

        return response()->json($companies);
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json(['data' => $company]);
    }

    /**
     * Get scraping statistics for the dashboard.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_companies' => Company::count(),
                'today_scraped' => Company::whereDate('scraped_at', today())->count(),
                'notifications_sent' => Company::where('notification_sent', true)->count(),
                'pending_notifications' => Company::where('notification_sent', false)->count(),
                'provinces' => Company::distinct('province')->pluck('province')->filter()->values(),
            ],
        ]);
    }
}
