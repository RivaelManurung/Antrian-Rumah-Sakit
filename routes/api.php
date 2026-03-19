<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Master\DoctorController;
use App\Http\Controllers\Master\DoctorScheduleController;
use App\Http\Controllers\Master\PoliController;
use App\Http\Controllers\Master\RoomController;
use App\Http\Controllers\Patient\PatientController;
use App\Http\Controllers\Queue\QueueActionController;
use App\Http\Controllers\Queue\QueueController;
use App\Http\Controllers\Queue\QueueCounterController;
use App\Http\Controllers\Queue\QueueDisplayController;
use App\Http\Controllers\System\HealthCheckController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthCheckController::class);

Route::prefix('auth')->middleware('web')->group(function () {
    Route::post('/login', LoginController::class);

    Route::middleware('auth')->group(function () {
        Route::post('/logout', LogoutController::class);
        Route::get('/me', MeController::class);
    });
});

Route::prefix('queue-displays')->group(function () {
    Route::get('/rooms/{roomId}', [QueueDisplayController::class, 'room']);
    Route::get('/overview', [QueueDisplayController::class, 'overview']);
    Route::get('/recent-calls', [QueueDisplayController::class, 'recentCalls']);
});

$adminMiddleware = ['web', 'auth'];

Route::middleware($adminMiddleware)->group(function () {
    Route::get('/dashboard', DashboardController::class);

    Route::apiResource('polies', PoliController::class);

    Route::apiResource('rooms', RoomController::class)->except(['show']);
    Route::patch('/rooms/{id}/toggle-active', [RoomController::class, 'toggleActive']);

    Route::apiResource('doctors', DoctorController::class)->except(['show']);
    Route::patch('/doctors/{id}/toggle-active', [DoctorController::class, 'toggleActive']);

    Route::get('/doctor-schedules', [DoctorScheduleController::class, 'index']);
    Route::post('/doctor-schedules', [DoctorScheduleController::class, 'store']);
    Route::put('/doctor-schedules/{id}', [DoctorScheduleController::class, 'update']);
    Route::post('/doctor-schedules/bulk-upsert', [DoctorScheduleController::class, 'bulkUpsert']);
    Route::delete('/doctor-schedules/{id}', [DoctorScheduleController::class, 'destroy']);

    Route::apiResource('patients', PatientController::class);

    Route::get('/queues', [QueueController::class, 'index']);
    Route::post('/queues', [QueueController::class, 'store']);
    Route::get('/queues/{id}', [QueueController::class, 'show']);
    Route::get('/queues/{id}/estimate-wait', [QueueController::class, 'estimateWait']);

    Route::post('/queue-actions/call-next', [QueueActionController::class, 'callNext']);
    Route::post('/queue-actions/{queueId}/recall', [QueueActionController::class, 'recall']);
    Route::post('/queue-actions/{queueId}/skip', [QueueActionController::class, 'skip']);
    Route::post('/queue-actions/{queueId}/done', [QueueActionController::class, 'done']);
    Route::post('/queue-actions/{queueId}/cancel', [QueueActionController::class, 'cancel']);

    Route::get('/queue-counters', [QueueCounterController::class, 'index']);
    Route::get('/queue-counters/rooms/{roomId}', [QueueCounterController::class, 'show']);
    Route::post('/queue-counters/rooms/{roomId}/sync', [QueueCounterController::class, 'sync']);
    Route::post('/queue-counters/rooms/{roomId}/reset', [QueueCounterController::class, 'reset']);
});
