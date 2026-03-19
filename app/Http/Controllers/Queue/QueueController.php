<?php

namespace App\Http\Controllers\Queue;

use App\Http\Controllers\Controller;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function __construct(private readonly QueueService $queueService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $queueDate = $request->query('queue_date', now()->toDateString());

        $query = DB::table('queues')
            ->join('polies', 'polies.id', '=', 'queues.poli_id')
            ->join('rooms', 'rooms.id', '=', 'queues.room_id')
            ->leftJoin('doctors', 'doctors.id', '=', 'queues.doctor_id')
            ->leftJoin('patients', 'patients.id', '=', 'queues.patient_id')
            ->select(
                'queues.*',
                'polies.name as poli_name',
                'rooms.name as room_name',
                'doctors.name as doctor_name',
                'patients.name as patient_name'
            )
            ->whereDate('queues.queue_date', $queueDate)
            ->orderBy('queues.number');

        foreach (['poli_id', 'room_id', 'doctor_id'] as $field) {
            if ($request->filled($field)) {
                $query->where("queues.{$field}", (int) $request->query($field));
            }
        }

        if ($request->filled('status')) {
            $query->where('queues.status', (string) $request->query('status'));
        }

        return response()->json($query->paginate((int) $request->query('per_page', 30)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'poli_id' => ['required', 'integer', 'exists:polies,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'doctor_id' => ['nullable', 'integer', 'exists:doctors,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'queue_date' => ['nullable', 'date'],
        ]);

        $queue = $this->queueService->createQueue($data);

        return response()->json([
            'message' => 'Nomor antrian berhasil dibuat.',
            'data' => $queue,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $queue = DB::table('queues')
            ->join('polies', 'polies.id', '=', 'queues.poli_id')
            ->join('rooms', 'rooms.id', '=', 'queues.room_id')
            ->leftJoin('doctors', 'doctors.id', '=', 'queues.doctor_id')
            ->leftJoin('patients', 'patients.id', '=', 'queues.patient_id')
            ->select(
                'queues.*',
                'polies.name as poli_name',
                'rooms.name as room_name',
                'doctors.name as doctor_name',
                'patients.name as patient_name'
            )
            ->where('queues.id', $id)
            ->first();

        if (! $queue) {
            return response()->json(['message' => 'Antrian tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $queue]);
    }

    public function estimateWait(int $id): JsonResponse
    {
        $estimate = $this->queueService->estimateWait($id);

        if (! $estimate) {
            return response()->json(['message' => 'Antrian tidak ditemukan.'], 404);
        }

        return response()->json([
            'data' => $estimate,
        ]);
    }
}
