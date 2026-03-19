<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('patients')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('identity_number', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'identity_number' => ['nullable', 'string', 'max:50', Rule::unique('patients', 'identity_number')],
        ]);

        $id = DB::table('patients')->insertGetId([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'identity_number' => $data['identity_number'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pasien berhasil dibuat.',
            'data' => DB::table('patients')->find($id),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $patient = DB::table('patients')->find($id);

        if (! $patient) {
            return response()->json(['message' => 'Pasien tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $patient]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $patient = DB::table('patients')->find($id);

        if (! $patient) {
            return response()->json(['message' => 'Pasien tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'identity_number' => ['nullable', 'string', 'max:50', Rule::unique('patients', 'identity_number')->ignore($id)],
        ]);

        DB::table('patients')->where('id', $id)->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'identity_number' => $data['identity_number'] ?? null,
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pasien berhasil diubah.',
            'data' => DB::table('patients')->find($id),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $patient = DB::table('patients')->find($id);

        if (! $patient) {
            return response()->json(['message' => 'Pasien tidak ditemukan.'], 404);
        }

        if (DB::table('queues')->where('patient_id', $id)->exists()) {
            return response()->json([
                'message' => 'Pasien tidak dapat dihapus karena sudah memiliki histori antrian.',
            ], 422);
        }

        DB::table('patients')->where('id', $id)->delete();

        return response()->json(['message' => 'Pasien berhasil dihapus.']);
    }
}
