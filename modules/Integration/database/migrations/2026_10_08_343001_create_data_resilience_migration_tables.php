<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_change_governance_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_code')->unique();
            $table->string('table_name');
            $table->boolean('is_backward_compatible')->default(true); // 343.2 & 343.4
            $table->boolean('has_backfill_plan')->default(false); // 343.2, 343.4, 343.6 Risk
            $table->boolean('ci_gate_approved')->default(false);
            $table->timestamps();
        });

        Schema::create('disaster_recovery_restore_drills', function (Blueprint $table) {
            $table->id();
            $table->string('drill_code')->unique();
            $table->string('database_cluster');
            $table->decimal('measured_rto_minutes', 8, 2);
            $table->decimal('target_rto_minutes', 8, 2)->default(60.00);
            $table->boolean('validation_suite_passed')->default(false); // 343.3 & 343.4
            $table->boolean('is_blocker_failure')->default(false); // 343.5 Edge case
            $table->boolean('certified_for_production_dr')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disaster_recovery_restore_drills');
        Schema::dropIfExists('schema_change_governance_proposals');
    }
};
