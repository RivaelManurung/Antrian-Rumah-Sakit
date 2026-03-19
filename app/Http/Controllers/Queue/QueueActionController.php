<?php

namespace App\Http\Controllers\Queue;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueActionController extends Controller
{
    public function callNext(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'queue_date' => ['nullable', 'date'],
        ]);

        $queue = DB::transaction(function () use ($request, $data) {
            $queueDate = $data['queue_date'] ?? now()->toDateString();
            $roomId = (int) $data['room_id'];
            $performedBy = $request->user()?->id;

            $existingServing = DB::table('queues')
                ->whereDate('queue_date', $queueDate)
                ->where('room_id', $roomId)
                ->where('status', 'serving')
                ->lockForUpdate()
                ->first();

            if ($existingServing) {
                throw ValidationException::withMessages([
                    'room_id' => ['Masih ada antrian yang sedang dilayani. Selesaikan dulu sebelum memanggil berikutnya.'],
                ]);
            }

            $nextQueue = DB::table('queues')
                ->whereDate('queue_date', $queueDate)
                ->where('room_id', $roomId)
                ->where('status', 'waiting')
                ->orderBy('number')
                ->lockForUpdate()
                ->first();

            if (! $nextQueue) {
                throw ValidationException::withMessages([
                    'queue' => ['Tidak ada antrian waiting untuk dipanggil.'],
                ]);
            }

            DB::table('queues')->where('id', $nextQueue->id)->update([
                'status' => 'serving',
                'called_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('queue_counters')->updateOrInsert(
                ['room_id' => $roomId],
                [
                    'current_queue_id' => $nextQueue->id,
                    'last_called_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $this->writeLog($nextQueue->id, 'called', $performedBy, 'Panggil antrian berikutnya');

            return DB::table('queues')->find($nextQueue->id);
        });

        return response()->json([
            'message' => 'Antrian berikutnya berhasil dipanggil.',
            'data' => $queue,
        ]);
    }

    public function recall(Request $request, int $queueId): JsonResponse
    {
        $queue = DB::table('queues')->where('id', $queueId)->first();

        if (! $queue) {
            return response()->json(['message' => 'Antrian tidak ditemukan.'], 404);
        }

        if (! in_array($queue->status, ['serving', 'waiting'], true)) {
            return response()->json(['message' => 'Hanya antrian waiting/serving yang dapat dipanggil ulang.'], 422);
        }

        DB::transaction(function () use ($request, $queue) {
            DB::table('queues')->where('id', $queue->id)->update([
                'status' => 'serving',
                'called_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('queue_counters')->updateOrInsert(
                ['room_id' => $queue->room_id],
                [
                    'current_queue_id' => $queue->id,
                    'last_called_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $this->writeLog($queue->id, 'recalled', $request->user()?->id, 'Panggil ulang antrian');
        });

        return response()->json([
            'message' => 'Antrian berhasil dipanggil ulang.',
            'data' => DB::table('queues')->find($queue->id),
        ]);
    }

    public function skip(Request $request, int $queueId): JsonResponse
    {
        return $this->transitionToFinalState(
            $request,
            $queueId,
            'skipped',
            'skipped',
            'Antrian berhasil di-skip.'
        );
    }

    public function done(Request $request, int $queueId): JsonResponse
    {
        return $this->transitionToFinalState(
            $request,
            $queueId,
            'done',
            'done',
            'Antrian berhasil diselesaikan.'
        );
    }

    public function cancel(Request $request, int $queueId): JsonResponse
    {
        return $this->transitionToFinalState(
            $request,
            $queueId,
            'cancelled',
            'cancelled',
            'Antrian berhasil dibatalkan.'
        );
    }

    private function transitionToFinalState(
        Request $request,
        int $queueId,
        string $targetStatus,
        string $logAction,
        string $message
    ): JsonResponse {
        $queue = DB::table('queues')->where('id', $queueId)->first();

        if (! $queue) {
            return response()->json(['message' => 'Antrian tidak ditemukan.'], 404);
        }

        if (in_array($queue->status, ['done', 'cancelled'], true)) {
            return response()->json(['message' => 'Antrian sudah final dan tidak dapat diubah.'], 422);
        }

        DB::transaction(function () use ($request, $queue, $targetStatus, $logAction) {
            DB::table('queues')->where('id', $queue->id)->update([
                'status' => $targetStatus,
                'finished_at' => in_array($targetStatus, ['done', 'cancelled'], true) ? now() : null,
                'updated_at' => now(),
            ]);

            DB::table('queue_counters')
                ->where('room_id', $queue->room_id)
                ->where('current_queue_id', $queue->id)
                ->update([
                    'current_queue_id' => null,
                    'updated_at' => now(),
                ]);

            $this->writeLog($queue->id, $logAction, $request->user()?->id, null);
        });

        return response()->json([
            'message' => $message,
            'data' => DB::table('queues')->find($queueId),
        ]);
    }

    private function writeLog(int $queueId, string $action, ?int $performedBy, ?string $note): void
    {
        DB::table('queue_logs')->insert([
            'queue_id' => $queueId,
            'action' => $action,
            'performed_by' => $performedBy,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
