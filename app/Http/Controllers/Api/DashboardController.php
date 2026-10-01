<?php

namespace App\Http\Controllers\Api;

use App\Core\DashboardService;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request, DashboardService $dashboard): JsonResponse
    {
        return ApiResponse::ok($dashboard->summary($request->user()));
    }
}
