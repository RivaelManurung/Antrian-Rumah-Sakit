<?php

namespace App\Http\Controllers\Queue;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueDisplayController extends Controller
{
    public function room(int $roomId, Request $request): JsonResponse
    {
        $queueDate = $request->query('queue_date', now()->toDateString());

        $room = DB::table('rooms')
            ->join('polies', 'polies.id', '=', 'rooms.poli_id')
            ->select('rooms.*', 'polies.name as poli_name', 'polies.code as poli_code')
            ->where('rooms.id', $roomId)
            ->first();

        if (! $room) {
            return response()->json(['message' => 'Ruang tidak ditemukan.'], 404);
        }

        $counter = DB::table('queue_counters')
            ->leftJoin('queues', 'queues.id', '=', 'queue_counters.current_queue_id')
            ->where('queue_counters.room_id', $roomId)
            ->select('queue_counters.*', 'queues.code as current_code', 'queues.number as current_number')
            ->first();

        $nextQueues = DB::table('queues')
            ->whereDate('queue_date', $queueDate)
            ->where('room_id', $roomId)
            ->where('status', 'waiting')
            ->orderBy('number')
            ->limit((int) $request->query('limit', 10))
            ->get(['id', 'code', 'number', 'status']);

        return response()->json([
            'data' => [
                'room' => $room,
                'counter' => $counter,
                'next_queues' => $nextQueues,
            ],
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $queueDate = $request->query('queue_date', now()->toDateString());

        $rooms = DB::table('rooms')
            ->join('polies', 'polies.id', '=', 'rooms.poli_id')
            ->leftJoin('queue_counters', 'queue_counters.room_id', '=', 'rooms.id')
            ->leftJoin('queues as current_queue', 'current_queue.id', '=', 'queue_counters.current_queue_id')
            ->select(
                'rooms.id',
                'rooms.name as room_name',
                'rooms.code as room_code',
                'polies.name as poli_name',
                'polies.code as poli_code',
                'current_queue.id as current_queue_id',
                'current_queue.code as current_queue_code',
                'queue_counters.last_called_at'
            )
            ->where('rooms.is_active', true)
            ->orderBy('polies.name')
            ->orderBy('rooms.name')
            ->get();

        $waitingCounts = DB::table('queues')
            ->selectRaw('room_id, COUNT(*) as waiting_count')
            ->whereDate('queue_date', $queueDate)
            ->where('status', 'waiting')
            ->groupBy('room_id')
            ->pluck('waiting_count', 'room_id');

        $rows = $rooms->map(function ($room) use ($waitingCounts) {
            $room->waiting_count = (int) ($waitingCounts[$room->id] ?? 0);
            return $room;
        });

        return response()->json(['data' => $rows]);
    }

    public function recentCalls(Request $request): JsonResponse
    {
        $queueDate = $request->query('queue_date', now()->toDateString());

        $rows = DB::table('queue_logs')
            ->join('queues', 'queues.id', '=', 'queue_logs.queue_id')
            ->join('rooms', 'rooms.id', '=', 'queues.room_id')
            ->whereDate('queues.queue_date', $queueDate)
            ->whereIn('queue_logs.action', ['called', 'recalled'])
            ->orderByDesc('queue_logs.created_at')
            ->limit((int) $request->query('limit', 30))
            ->get([
                'queue_logs.id',
                'queue_logs.action',
                'queue_logs.created_at',
                'queues.id as queue_id',
                'queues.code as queue_code',
                'rooms.name as room_name',
            ]);

        return response()->json(['data' => $rows]);
    }
}
