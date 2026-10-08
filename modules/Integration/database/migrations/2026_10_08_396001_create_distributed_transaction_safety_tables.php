<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_stress_failover_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('global_key')->unique();
            $table->string('active_region');
            $table->integer('posted_count')->default(1); // 396.1 & 396.4
            $table->timestamps();
        });

        Schema::create('global_stress_distributed_saga_states', function (Blueprint $table) {
            $table->id();
            $table->string('saga_code')->unique();
            $table->string('saga_status'); // PENDING, TIMED_OUT, ESCALATED_MANUAL_REVIEW
            $table->boolean('manual_owner_escalated')->default(false); // 396.2 & 396.5 Edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_stress_distributed_saga_states');
        Schema::dropIfExists('global_stress_failover_idempotency_keys');
    }
};
