<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $base = DB::table('queues')->whereDate('queue_date', $date);

        $summary = [
            'total' => (clone $base)->count(),
            'waiting' => (clone $base)->where('status', 'waiting')->count(),
            'serving' => (clone $base)->where('status', 'serving')->count(),
            'done' => (clone $base)->where('status', 'done')->count(),
            'skipped' => (clone $base)->where('status', 'skipped')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
        ];

        $perPoli = DB::table('queues')
            ->join('polies', 'polies.id', '=', 'queues.poli_id')
            ->whereDate('queues.queue_date', $date)
            ->groupBy('queues.poli_id', 'polies.name')
            ->orderBy('polies.name')
            ->selectRaw('queues.poli_id, polies.name as poli_name, COUNT(*) as total')
            ->get();

        $last7DaysTrend = DB::table('queues')
            ->whereDate('queue_date', '>=', now()->subDays(6)->toDateString())
            ->groupBy('queue_date')
            ->orderBy('queue_date')
            ->selectRaw('queue_date, COUNT(*) as total')
            ->get();

        return response()->json([
            'data' => [
                'date' => $date,
                'summary' => $summary,
                'per_poli' => $perPoli,
                'last_7_days_trend' => $last7DaysTrend,
            ],
        ]);
    }
}
