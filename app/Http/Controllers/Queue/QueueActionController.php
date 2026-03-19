<?php

namespace App\Http\Controllers\Queue;

use App\Http\Controllers\Controller;
use App\Services\QueueActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueActionController extends Controller
{
    public function __construct(private readonly QueueActionService $queueActionService)
    {
    }

    public function callNext(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'queue_date' => ['nullable', 'date'],
        ]);

        $queue = $this->queueActionService->callNext(
            (int) $data['room_id'],
            $data['queue_date'] ?? null,
            $request->user()?->id
        );

        return response()->json([
            'message' => 'Antrian berikutnya berhasil dipanggil.',
            'data' => $queue,
        ]);
    }

    public function recall(Request $request, int $queueId): JsonResponse
    {
        $queue = $this->queueActionService->recall($queueId, $request->user()?->id);

        if (! $queue) {
            return response()->json(['message' => 'Antrian tidak ditemukan.'], 404);
        }

        return response()->json([
            'message' => 'Antrian berhasil dipanggil ulang.',
            'data' => $queue,
        ]);
    }

    public function skip(Request $request, int $queueId): JsonResponse
    {
        $queue = $this->queueActionService->skip($queueId, $request->user()?->id);

        if (! $queue) {
            return response()->json(['message' => 'Antrian tidak ditemukan.'], 404);
        }

        return response()->json([
            'message' => 'Antrian berhasil di-skip.',
            'data' => $queue,
        ]);
    }

    public function done(Request $request, int $queueId): JsonResponse
    {
        $queue = $this->queueActionService->done($queueId, $request->user()?->id);

        if (! $queue) {
            return response()->json(['message' => 'Antrian tidak ditemukan.'], 404);
        }

        return response()->json([
            'message' => 'Antrian berhasil diselesaikan.',
            'data' => $queue,
        ]);
    }

    public function cancel(Request $request, int $queueId): JsonResponse
    {
        $queue = $this->queueActionService->cancel($queueId, $request->user()?->id);

        if (! $queue) {
            return response()->json(['message' => 'Antrian tidak ditemukan.'], 404);
        }

        return response()->json([
            'message' => 'Antrian berhasil dibatalkan.',
            'data' => $queue,
        ]);
    }
}
