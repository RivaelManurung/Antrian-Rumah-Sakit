<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PoliController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = DB::table('polies')
            ->select('polies.*')
            ->selectSub(function ($sub) {
                $sub->from('rooms')->selectRaw('COUNT(*)')->whereColumn('rooms.poli_id', 'polies.id');
            }, 'rooms_count')
            ->selectSub(function ($sub) {
                $sub->from('doctors')->selectRaw('COUNT(*)')->whereColumn('doctors.poli_id', 'polies.id');
            }, 'doctors_count')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate((int) $request->query('per_page', 15)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', 'unique:polies,code'],
        ]);

        $id = DB::table('polies')->insertGetId([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Poli berhasil dibuat.',
            'data' => DB::table('polies')->find($id),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $poli = DB::table('polies')->find($id);

        if (! $poli) {
            return response()->json(['message' => 'Poli tidak ditemukan.'], 404);
        }

        return response()->json([
            'data' => [
                'poli' => $poli,
                'rooms' => DB::table('rooms')->where('poli_id', $id)->orderBy('name')->get(),
                'doctors' => DB::table('doctors')->where('poli_id', $id)->orderBy('name')->get(),
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $poli = DB::table('polies')->find($id);

        if (! $poli) {
            return response()->json(['message' => 'Poli tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', Rule::unique('polies', 'code')->ignore($id)],
        ]);

        DB::table('polies')->where('id', $id)->update([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Poli berhasil diubah.',
            'data' => DB::table('polies')->find($id),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $poli = DB::table('polies')->find($id);

        if (! $poli) {
            return response()->json(['message' => 'Poli tidak ditemukan.'], 404);
        }

        $hasRelations = DB::table('rooms')->where('poli_id', $id)->exists()
            || DB::table('doctors')->where('poli_id', $id)->exists()
            || DB::table('queues')->where('poli_id', $id)->exists();

        if ($hasRelations) {
            return response()->json([
                'message' => 'Poli tidak dapat dihapus karena masih memiliki relasi data.',
            ], 422);
        }

        DB::table('polies')->where('id', $id)->delete();

        return response()->json(['message' => 'Poli berhasil dihapus.']);
    }
}
