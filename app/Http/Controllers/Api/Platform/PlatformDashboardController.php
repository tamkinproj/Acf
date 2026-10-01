<?php

namespace App\Http\Controllers\Api\Platform;

use App\Core\Platform\PlatformStats;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlatformDashboardController extends Controller
{
    public function summary(PlatformStats $stats): JsonResponse
    {
        return ApiResponse::ok($stats->summary());
    }
}
