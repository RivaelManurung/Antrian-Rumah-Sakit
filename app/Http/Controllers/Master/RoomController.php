<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('rooms')
            ->join('polies', 'polies.id', '=', 'rooms.poli_id')
            ->select('rooms.*', 'polies.name as poli_name', 'polies.code as poli_code')
            ->orderBy('rooms.name');

        if ($request->filled('poli_id')) {
            $query->where('rooms.poli_id', (int) $request->query('poli_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('rooms.is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('rooms.name', 'like', "%{$search}%")
                    ->orWhere('rooms.code', 'like', "%{$search}%")
                    ->orWhere('polies.name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'poli_id' => ['required', 'integer', 'exists:polies,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', 'unique:rooms,code'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $id = DB::table('rooms')->insertGetId([
            'poli_id' => $data['poli_id'],
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Ruang berhasil dibuat.',
            'data' => DB::table('rooms')->find($id),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $room = DB::table('rooms')->find($id);

        if (! $room) {
            return response()->json(['message' => 'Ruang tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'poli_id' => ['required', 'integer', 'exists:polies,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', Rule::unique('rooms', 'code')->ignore($id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        DB::table('rooms')->where('id', $id)->update([
            'poli_id' => $data['poli_id'],
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'is_active' => $data['is_active'] ?? $room->is_active,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Ruang berhasil diubah.',
            'data' => DB::table('rooms')->find($id),
        ]);
    }

    public function toggleActive(int $id): JsonResponse
    {
        $room = DB::table('rooms')->find($id);

        if (! $room) {
            return response()->json(['message' => 'Ruang tidak ditemukan.'], 404);
        }

        DB::table('rooms')->where('id', $id)->update([
            'is_active' => ! (bool) $room->is_active,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Status ruang berhasil diperbarui.',
            'data' => DB::table('rooms')->find($id),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $room = DB::table('rooms')->find($id);

        if (! $room) {
            return response()->json(['message' => 'Ruang tidak ditemukan.'], 404);
        }

        $hasRelations = DB::table('doctor_schedules')->where('room_id', $id)->exists()
            || DB::table('queues')->where('room_id', $id)->exists()
            || DB::table('queue_counters')->where('room_id', $id)->exists();

        if ($hasRelations) {
            return response()->json([
                'message' => 'Ruang tidak dapat dihapus karena masih memiliki relasi data.',
            ], 422);
        }

        DB::table('rooms')->where('id', $id)->delete();

        return response()->json(['message' => 'Ruang berhasil dihapus.']);
    }
}
