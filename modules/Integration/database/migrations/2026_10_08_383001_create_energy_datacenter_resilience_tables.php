<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_data_center_workload_placements', function (Blueprint $table) {
            $table->id();
            $table->string('workload_code')->unique();
            $table->string('target_jurisdiction');
            $table->string('data_residency_jurisdiction');
            $table->boolean('residency_constraint_enforced')->default(true); // 383.1 & 383.4
            $table->boolean('is_critical_priority')->default(false); // 383.2 & 383.4
            $table->string('placement_status'); // PLACED, REJECTED
            $table->timestamps();
        });

        Schema::create('global_grid_event_resilience_plans', function (Blueprint $table) {
            $table->id();
            $table->string('event_code')->unique();
            $table->string('grid_status'); // EMERGENCY, NORMAL
            $table->boolean('critical_workloads_sheltered')->default(true); // 383.2, 383.4, 383.5 Edge case
            $table->boolean('non_critical_throttled_with_notice')->default(true); // 383.5 Edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_grid_event_resilience_plans');
        Schema::dropIfExists('global_data_center_workload_placements');
    }
};
