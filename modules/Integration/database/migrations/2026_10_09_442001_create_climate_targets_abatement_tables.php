<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esg_climate_milestones', function (Blueprint $table) {
            $table->id();
            $table->string('milestone_code')->unique();
            $table->string('target_year');
            $table->decimal('baseline_emissions_mt', 15, 2);
            $table->decimal('target_reduction_mt', 15, 2);
            $table->decimal('actual_reduction_mt', 15, 2)->default(0.00);
            $table->boolean('is_missed')->default(false); // 442.3, 442.5
            $table->boolean('corrective_action_opened')->default(false); // 442.4, 442.5
            $table->string('corrective_action_ticket')->nullable();
            $table->timestamps();
        });

        Schema::create('esg_carbon_abatement_curves', function (Blueprint $table) {
            $table->id();
            $table->string('measure_code')->unique();
            $table->string('measure_title');
            $table->decimal('abatement_potential_mt', 12, 2);
            $table->decimal('cost_per_tonne_usd', 10, 2); // 442.2 ranking
            $table->string('curve_version')->default('1.0');
            $table->string('scope_tier'); // scope_1, scope_2, scope_3
            $table->string('data_confidence_label')->default('HIGH'); // 442.6 risk
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esg_carbon_abatement_curves');
        Schema::dropIfExists('esg_climate_milestones');
    }
};
