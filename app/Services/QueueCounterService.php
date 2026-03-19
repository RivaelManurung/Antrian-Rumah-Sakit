<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class QueueCounterService
{
    public function sync(int $roomId, ?string $queueDate = null): object
    {
        $targetDate = $queueDate ?: now()->toDateString();

        $currentServing = DB::table('queues')
            ->whereDate('queue_date', $targetDate)
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

        return DB::table('queue_counters')->where('room_id', $roomId)->first();
    }

    public function reset(int $roomId): object
    {
        DB::table('queue_counters')->updateOrInsert(
            ['room_id' => $roomId],
            [
                'current_queue_id' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return DB::table('queue_counters')->where('room_id', $roomId)->first();
    }
}
