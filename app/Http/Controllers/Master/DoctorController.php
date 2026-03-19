<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('doctors')
            ->join('polies', 'polies.id', '=', 'doctors.poli_id')
            ->leftJoin('users', 'users.id', '=', 'doctors.user_id')
            ->select(
                'doctors.*',
                'polies.name as poli_name',
                'polies.code as poli_code',
                'users.email as user_email'
            )
            ->orderBy('doctors.name');

        if ($request->filled('poli_id')) {
            $query->where('doctors.poli_id', (int) $request->query('poli_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('doctors.is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('doctors.name', 'like', "%{$search}%")
                    ->orWhere('polies.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'poli_id' => ['required', 'integer', 'exists:polies,id'],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $id = DB::table('doctors')->insertGetId([
            'user_id' => $data['user_id'] ?? null,
            'poli_id' => $data['poli_id'],
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Dokter berhasil dibuat.',
            'data' => DB::table('doctors')->find($id),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $doctor = DB::table('doctors')->find($id);

        if (! $doctor) {
            return response()->json(['message' => 'Dokter tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'poli_id' => ['required', 'integer', 'exists:polies,id'],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        DB::table('doctors')->where('id', $id)->update([
            'user_id' => $data['user_id'] ?? null,
            'poli_id' => $data['poli_id'],
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? $doctor->is_active,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Dokter berhasil diubah.',
            'data' => DB::table('doctors')->find($id),
        ]);
    }

    public function toggleActive(int $id): JsonResponse
    {
        $doctor = DB::table('doctors')->find($id);

        if (! $doctor) {
            return response()->json(['message' => 'Dokter tidak ditemukan.'], 404);
        }

        DB::table('doctors')->where('id', $id)->update([
            'is_active' => ! (bool) $doctor->is_active,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Status dokter berhasil diperbarui.',
            'data' => DB::table('doctors')->find($id),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $doctor = DB::table('doctors')->find($id);

        if (! $doctor) {
            return response()->json(['message' => 'Dokter tidak ditemukan.'], 404);
        }

        $hasRelations = DB::table('doctor_schedules')->where('doctor_id', $id)->exists()
            || DB::table('queues')->where('doctor_id', $id)->exists();

        if ($hasRelations) {
            return response()->json([
                'message' => 'Dokter tidak dapat dihapus karena masih memiliki relasi data.',
            ], 422);
        }

        DB::table('doctors')->where('id', $id)->delete();

        return response()->json(['message' => 'Dokter berhasil dihapus.']);
    }
}
