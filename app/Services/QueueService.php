<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueService
{
    public function createQueue(array $data): object
    {
        return DB::transaction(function () use ($data) {
            $queueDate = $data['queue_date'] ?? now()->toDateString();

            $room = DB::table('rooms')->where('id', $data['room_id'])->first();
            if (! $room || (int) $room->poli_id !== (int) $data['poli_id']) {
                throw ValidationException::withMessages([
                    'room_id' => ['Ruang tidak sesuai dengan poli yang dipilih.'],
                ]);
            }

            if (! (bool) $room->is_active) {
                throw ValidationException::withMessages([
                    'room_id' => ['Ruang sedang tidak aktif.'],
                ]);
            }

            if (! empty($data['doctor_id'])) {
                $doctor = DB::table('doctors')->where('id', $data['doctor_id'])->first();
                if (! $doctor || (int) $doctor->poli_id !== (int) $data['poli_id']) {
                    throw ValidationException::withMessages([
                        'doctor_id' => ['Dokter tidak sesuai dengan poli yang dipilih.'],
                    ]);
                }
            }

            $sequence = DB::table('queue_sequences')
                ->where('poli_id', $data['poli_id'])
                ->whereDate('queue_date', $queueDate)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DB::table('queue_sequences')->insert([
                    'poli_id' => $data['poli_id'],
                    'queue_date' => $queueDate,
                    'last_number' => 0,
                ]);

                $sequence = DB::table('queue_sequences')
                    ->where('poli_id', $data['poli_id'])
                    ->whereDate('queue_date', $queueDate)
                    ->lockForUpdate()
                    ->first();
            }

            $nextNumber = ((int) $sequence->last_number) + 1;

            DB::table('queue_sequences')
                ->where('id', $sequence->id)
                ->update(['last_number' => $nextNumber]);

            $poli = DB::table('polies')->where('id', $data['poli_id'])->first();
            $code = strtoupper((string) $poli->code).str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

            $id = DB::table('queues')->insertGetId([
                'code' => $code,
                'number' => $nextNumber,
                'poli_id' => $data['poli_id'],
                'room_id' => $data['room_id'],
                'doctor_id' => $data['doctor_id'] ?? null,
                'patient_id' => $data['patient_id'] ?? null,
                'queue_date' => $queueDate,
                'status' => 'waiting',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('queues')->find($id);
        });
    }

    public function estimateWait(int $queueId): ?array
    {
        $queue = DB::table('queues')->where('id', $queueId)->first();

        if (! $queue) {
            return null;
        }

        $aheadCount = DB::table('queues')
            ->whereDate('queue_date', $queue->queue_date)
            ->where('room_id', $queue->room_id)
            ->whereIn('status', ['waiting', 'serving'])
            ->where('number', '<', $queue->number)
            ->count();

        $avgServiceMinutes = DB::table('queues')
            ->whereDate('queue_date', $queue->queue_date)
            ->where('room_id', $queue->room_id)
            ->whereNotNull('called_at')
            ->whereNotNull('finished_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, called_at, finished_at)) as avg_min')
            ->value('avg_min');

        $avgServiceMinutes = max((int) round((float) ($avgServiceMinutes ?? 7)), 1);

        return [
            'queue_id' => $queue->id,
            'ahead_count' => $aheadCount,
            'average_service_minutes' => $avgServiceMinutes,
            'estimated_wait_minutes' => $aheadCount * $avgServiceMinutes,
        ];
    }
}
