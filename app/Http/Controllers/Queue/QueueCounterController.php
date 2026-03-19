<?php

namespace App\Http\Controllers\Queue;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueCounterController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = DB::table('queue_counters')
            ->join('rooms', 'rooms.id', '=', 'queue_counters.room_id')
            ->leftJoin('queues', 'queues.id', '=', 'queue_counters.current_queue_id')
            ->select(
                'queue_counters.*',
                'rooms.name as room_name',
                'rooms.code as room_code',
                'queues.code as current_queue_code',
                'queues.status as current_queue_status'
            )
            ->orderBy('rooms.name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function show(int $roomId): JsonResponse
    {
        $row = DB::table('queue_counters')
            ->join('rooms', 'rooms.id', '=', 'queue_counters.room_id')
            ->leftJoin('queues', 'queues.id', '=', 'queue_counters.current_queue_id')
            ->select(
                'queue_counters.*',
                'rooms.name as room_name',
                'rooms.code as room_code',
                'queues.code as current_queue_code',
                'queues.status as current_queue_status'
            )
            ->where('queue_counters.room_id', $roomId)
            ->first();

        if (! $row) {
            return response()->json(['message' => 'Counter ruang tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $row]);
    }

    public function sync(Request $request, int $roomId): JsonResponse
    {
        $queueDate = $request->query('queue_date', now()->toDateString());

        $currentServing = DB::table('queues')
            ->whereDate('queue_date', $queueDate)
            ->where('room_id', $roomId)
            ->where('status', 'serving')
            ->orderByDesc('updated_at')
            ->first();

        DB::table('queue_counters')->updateOrInsert(
            ['room_id' => $roomId],
            [
                'current_queue_id' => $currentServing?->id,
                'last_called_at' => $currentServing?->called_at,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Counter berhasil disinkronkan.',
            'data' => DB::table('queue_counters')->where('room_id', $roomId)->first(),
        ]);
    }

    public function reset(int $roomId): JsonResponse
    {
        DB::table('queue_counters')->updateOrInsert(
            ['room_id' => $roomId],
            [
                'current_queue_id' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Counter berhasil direset.',
            'data' => DB::table('queue_counters')->where('room_id', $roomId)->first(),
        ]);
    }
}
