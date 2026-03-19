<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueActionService
{
    public function callNext(int $roomId, ?string $queueDate, ?int $performedBy): object
    {
        return DB::transaction(function () use ($roomId, $queueDate, $performedBy) {
            $targetDate = $queueDate ?: now()->toDateString();

            $existingServing = DB::table('queues')
                ->whereDate('queue_date', $targetDate)
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
                ->whereDate('queue_date', $targetDate)
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
    }

    public function recall(int $queueId, ?int $performedBy): ?object
    {
        $queue = DB::table('queues')->where('id', $queueId)->first();

        if (! $queue) {
            return null;
        }

        if (! in_array($queue->status, ['serving', 'waiting'], true)) {
            throw ValidationException::withMessages([
                'queue' => ['Hanya antrian waiting/serving yang dapat dipanggil ulang.'],
            ]);
        }

        DB::transaction(function () use ($queue, $performedBy) {
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

            $this->writeLog($queue->id, 'recalled', $performedBy, 'Panggil ulang antrian');
        });

        return DB::table('queues')->find($queue->id);
    }

    public function skip(int $queueId, ?int $performedBy): ?object
    {
        return $this->transitionToFinalState($queueId, 'skipped', 'skipped', $performedBy);
    }

    public function done(int $queueId, ?int $performedBy): ?object
    {
        return $this->transitionToFinalState($queueId, 'done', 'done', $performedBy);
    }

    public function cancel(int $queueId, ?int $performedBy): ?object
    {
        return $this->transitionToFinalState($queueId, 'cancelled', 'cancelled', $performedBy);
    }

    private function transitionToFinalState(
        int $queueId,
        string $targetStatus,
        string $logAction,
        ?int $performedBy
    ): ?object {
        $queue = DB::table('queues')->where('id', $queueId)->first();

        if (! $queue) {
            return null;
        }

        if (in_array($queue->status, ['done', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'queue' => ['Antrian sudah final dan tidak dapat diubah.'],
            ]);
        }

        DB::transaction(function () use ($queue, $targetStatus, $logAction, $performedBy) {
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

            $this->writeLog($queue->id, $logAction, $performedBy, null);
        });

        return DB::table('queues')->find($queue->id);
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
