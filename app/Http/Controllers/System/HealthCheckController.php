<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbOk = true;
        $cacheOk = true;

        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        try {
            Cache::put('healthcheck_ping', now()->toISOString(), 10);
            Cache::get('healthcheck_ping');
        } catch (\Throwable) {
            $cacheOk = false;
        }

        $status = $dbOk && $cacheOk ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'services' => [
                'db' => $dbOk ? 'ok' : 'down',
                'cache' => $cacheOk ? 'ok' : 'down',
            ],
            'time' => now()->toISOString(),
        ], $status === 'ok' ? 200 : 503);
    }
}
