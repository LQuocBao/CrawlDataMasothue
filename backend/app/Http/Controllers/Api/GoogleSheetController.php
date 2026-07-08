<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleSheetService;
use Illuminate\Http\JsonResponse;

class GoogleSheetController extends Controller
{
    public function index(GoogleSheetService $service): JsonResponse
    {
        $sheets = $service->listSheets();

        return response()->json(['data' => $sheets]);
    }
}
