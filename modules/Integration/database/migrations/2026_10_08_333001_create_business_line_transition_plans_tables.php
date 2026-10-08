<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_line_climate_transition_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code')->unique();
            $table->string('line_code'); // LINE_MINING, LINE_SMELTER, LINE_CEMENT, etc.
            $table->decimal('baseline_emissions_tco2e', 15, 2);
            $table->decimal('target_abatement_tco2e', 15, 2);
            $table->decimal('allocated_transition_capex_usd', 18, 2);
            $table->boolean('has_feasible_transition_pathway')->default(true); // 333.5 Edge case
            $table->boolean('board_approved')->default(true);
            $table->timestamps();
        });

        Schema::create('transition_milestone_progress_trackers', function (Blueprint $table) {
            $table->id();
            $table->string('milestone_code')->unique();
            $table->string('plan_code')->index();
            $table->string('milestone_title');
            $table->date('due_date');
            $table->string('status'); // ON_TRACK, COMPLETED, DELAYED_MISSED
            $table->boolean('board_escalation_triggered')->default(false); // 333.3 & 333.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transition_milestone_progress_trackers');
        Schema::dropIfExists('business_line_climate_transition_plans');
    }
};
