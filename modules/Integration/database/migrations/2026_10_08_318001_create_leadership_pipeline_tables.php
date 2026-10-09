<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leadership_pipeline_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_code')->unique();
            $table->string('title');
            $table->string('tier_level'); // FIRST_LINE, DIRECTOR, C_SUITE
            $table->boolean('is_critical_role')->default(true);
            $table->integer('ready_successor_count')->default(0); // 318.3 Bench strength
            $table->boolean('board_bench_alert_triggered')->default(false); // 318.3 & 318.6 Risk
            $table->timestamps();
        });

        Schema::create('leadership_succession_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_code')->unique();
            $table->string('role_code')->index();
            $table->string('employee_id')->index();
            $table->decimal('readiness_score', 4, 1); // 0 - 100
            $table->boolean('is_ready_now')->default(false); // >= 85.0
            $table->boolean('requires_readiness_plan')->default(false); // 318.5 Edge case
            $table->string('readiness_plan_deadline')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leadership_succession_candidates');
        Schema::dropIfExists('leadership_pipeline_roles');
    }
};
