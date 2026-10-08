<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_queue_poison_quarantines', function (Blueprint $table) {
            $table->id();
            $table->string('job_code')->unique();
            $table->integer('retry_count');
            $table->integer('max_retry_budget')->default(3);
            $table->boolean('quarantined_to_dlq')->default(false); // 394.1, 394.4, 394.6 Risk
            $table->timestamps();
        });

        Schema::create('global_stress_batch_process_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code')->unique();
            $table->integer('processed_chunks')->default(0);
            $table->boolean('cancelled_mid_flight')->default(false);
            $table->boolean('partial_rollback_applied')->default(false); // 394.2 & 394.5 Edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_batch_process_checkpoints');
        Schema::dropIfExists('global_stress_queue_poison_quarantines');
    }
};
