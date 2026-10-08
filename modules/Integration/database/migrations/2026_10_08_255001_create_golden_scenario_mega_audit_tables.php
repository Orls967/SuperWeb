<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mega_simulation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('sim_run_code')->unique();
            $table->string('sim_type'); // GOLDEN_30_LINE, CRISIS_MEGA, MA_ACQUISITION
            $table->integer('days_simulated')->default(180);
            $table->integer('seed_value');
            $table->string('cryptographic_fingerprint'); // 255.7
            $table->integer('total_audits_passed_count')->default(80); // 255.2
            $table->integer('discrepancy_count')->default(0);
            $table->integer('dlq_count_at_end')->default(0); // 255.8
            $table->string('status')->default('RUNNING'); // RUNNING, COMPLETED, HALTED_ON_AUDIT_FAILURE (255.6)
            $table->timestamps();
        });

        Schema::create('mega_simulation_domain_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sim_run_id');
            $table->string('domain_line');
            $table->integer('day_offset');
            $table->string('milestone_event_name');
            $table->string('verified_hash');
            $table->timestamps();
        });

        Schema::create('mega_crisis_recovery_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sim_run_id');
            $table->string('crisis_type');
            $table->boolean('continuity_plan_activated')->default(true);
            $table->boolean('recovery_successful')->default(true);
            $table->boolean('zero_discrepancy_verified')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mega_crisis_recovery_records');
        Schema::dropIfExists('mega_simulation_domain_milestones');
        Schema::dropIfExists('mega_simulation_runs');
    }
};
