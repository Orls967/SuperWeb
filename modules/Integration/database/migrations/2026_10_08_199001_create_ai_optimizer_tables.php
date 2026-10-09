<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 199.1 & 199.2: Centralized optimization problems and hard constraint satisfaction
        Schema::create('ai_optimizer_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code')->unique();
            $table->string('domain_code'); // FLEET_ROUTING, SHIFT_SCHEDULING, SEAT_ALLOCATION
            $table->integer('solver_seed');
            $table->json('hard_constraints'); // [{max_hours: 8}, {max_capacity: 50}]
            $table->json('recommended_solution');
            $table->boolean('hard_constraints_satisfied')->default(true);
            $table->boolean('is_infeasible')->default(false);
            $table->string('shadow_mode_status')->default('SHADOW'); // SHADOW, PROD
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_optimizer_runs');
    }
};
