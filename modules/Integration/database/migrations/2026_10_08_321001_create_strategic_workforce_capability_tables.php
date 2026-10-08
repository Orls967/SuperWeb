<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_workforce_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code')->unique();
            $table->string('line_code'); // LINE_MINING, LINE_LOGISTICS, LINE_ENERGY
            $table->string('scenario_type'); // BASELINE, GROWTH, AUTOMATION, DISRUPTION
            $table->integer('projected_headcount_required');
            $table->decimal('projected_annual_cost_usd', 18, 2);
            $table->timestamps();
        });

        Schema::create('strategic_workforce_capacity_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_code')->unique();
            $table->string('scenario_code')->index();
            $table->string('role_code');
            $table->boolean('is_critical_role')->default(true);
            $table->integer('requested_fte');
            $table->integer('allocated_fte'); // 321.5 Edge case
            $table->integer('deferred_fte')->default(0);
            $table->string('deferred_plan_status')->default('NOT_DEFERRED'); // NOT_DEFERRED, FORMALLY_DEFERRED_WITH_PLAN
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_workforce_capacity_allocations');
        Schema::dropIfExists('strategic_workforce_scenarios');
    }
};
