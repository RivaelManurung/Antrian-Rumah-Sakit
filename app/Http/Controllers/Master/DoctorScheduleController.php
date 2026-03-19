<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('doctor_schedules')
            ->join('doctors', 'doctors.id', '=', 'doctor_schedules.doctor_id')
            ->join('rooms', 'rooms.id', '=', 'doctor_schedules.room_id')
            ->join('polies', 'polies.id', '=', 'doctors.poli_id')
            ->select(
                'doctor_schedules.*',
                'doctors.name as doctor_name',
                'rooms.name as room_name',
                'rooms.code as room_code',
                'polies.name as poli_name'
            )
            ->orderBy('doctor_schedules.day_of_week')
            ->orderBy('doctor_schedules.start_time');

        foreach (['doctor_id', 'room_id', 'day_of_week'] as $field) {
            if ($request->filled($field)) {
                $query->where("doctor_schedules.{$field}", (int) $request->query($field));
            }
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedSchedule($request);
        $this->ensureNoOverlap($data);

        $id = DB::table('doctor_schedules')->insertGetId([
            ...$data,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Jadwal dokter berhasil dibuat.',
            'data' => DB::table('doctor_schedules')->find($id),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $schedule = DB::table('doctor_schedules')->find($id);

        if (! $schedule) {
            return response()->json(['message' => 'Jadwal tidak ditemukan.'], 404);
        }

        $data = $this->validatedSchedule($request);
        $this->ensureNoOverlap($data, $id);

        DB::table('doctor_schedules')->where('id', $id)->update([
            ...$data,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Jadwal dokter berhasil diubah.',
            'data' => DB::table('doctor_schedules')->find($id),
        ]);
    }

    public function bulkUpsert(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'schedules.*.room_id' => ['required', 'integer', 'exists:rooms,id'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.quota' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($payload) {
            foreach ($payload['schedules'] as $item) {
                if ($item['start_time'] >= $item['end_time']) {
                    throw ValidationException::withMessages([
                        'schedules' => ['start_time harus lebih kecil dari end_time.'],
                    ]);
                }

                $this->ensureNoOverlap($item);

                DB::table('doctor_schedules')->updateOrInsert(
                    [
                        'doctor_id' => $item['doctor_id'],
                        'room_id' => $item['room_id'],
                        'day_of_week' => $item['day_of_week'],
                        'start_time' => $item['start_time'],
                    ],
                    [
                        'end_time' => $item['end_time'],
                        'quota' => $item['quota'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Bulk jadwal dokter berhasil disimpan.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $exists = DB::table('doctor_schedules')->where('id', $id)->exists();

        if (! $exists) {
            return response()->json(['message' => 'Jadwal tidak ditemukan.'], 404);
        }

        DB::table('doctor_schedules')->where('id', $id)->delete();

        return response()->json(['message' => 'Jadwal dokter berhasil dihapus.']);
    }

    private function validatedSchedule(Request $request): array
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'quota' => ['required', 'integer', 'min:1'],
        ]);

        if ($data['start_time'] >= $data['end_time']) {
            throw ValidationException::withMessages([
                'start_time' => ['start_time harus lebih kecil dari end_time.'],
            ]);
        }

        return $data;
    }

    private function ensureNoOverlap(array $data, ?int $ignoreId = null): void
    {
        $query = DB::table('doctor_schedules')
            ->where('doctor_id', $data['doctor_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_time', [$data['start_time'], $data['end_time']])
                    ->orWhereBetween('end_time', [$data['start_time'], $data['end_time']])
                    ->orWhere(function ($x) use ($data) {
                        $x->where('start_time', '<', $data['start_time'])
                            ->where('end_time', '>', $data['end_time']);
                    });
            });

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'schedule' => ['Jadwal overlap untuk dokter di hari yang sama.'],
            ]);
        }
    }
}
