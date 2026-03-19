<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->unsignedInteger('number');

            $table->foreignId('poli_id')->constrained('polies');
            $table->foreignId('room_id')->constrained('rooms');
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();

            $table->date('queue_date');

            $table->enum('status', ['waiting', 'serving', 'done', 'skipped', 'cancelled'])->default('waiting');

            $table->timestamp('called_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['queue_date', 'poli_id', 'room_id'], 'idx_queue');
            $table->index('status', 'idx_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
