<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_bcp_crisis_exercises', function (Blueprint $table) {
            $table->id();
            $table->string('exercise_code')->unique();
            $table->string('scenario_type'); // natural_disaster, cyber, supplier_failure, utility_outage (452.1)
            $table->decimal('target_rto_minutes', 10, 2);
            $table->decimal('actual_recovery_minutes', 10, 2)->nullable();
            $table->boolean('exercise_objectives_met')->default(false); // 452.2, 452.4
            $table->boolean('mandatory_re_drill_required')->default(false); // 452.5 edge case
            $table->text('after_action_review_summary')->nullable(); // 452.2, 452.7
            $table->string('status')->default('scheduled'); // scheduled, completed, failed_requires_redrill
            $table->timestamps();
        });

        Schema::create('gov_crisis_communications', function (Blueprint $table) {
            $table->id();
            $table->string('broadcast_code')->unique();
            $table->string('exercise_code');
            $table->string('target_audience'); // internal_staff, media, regulators, customers (452.3)
            $table->boolean('is_simulation_mode')->default(true); // 452.6 risk (containment)
            $table->boolean('spokesperson_chain_approved')->default(false); // 452.3, 452.4
            $table->boolean('is_released')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_crisis_communications');
        Schema::dropIfExists('gov_bcp_crisis_exercises');
    }
};
