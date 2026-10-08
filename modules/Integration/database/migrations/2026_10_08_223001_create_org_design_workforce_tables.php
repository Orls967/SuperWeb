<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_positions', function (Blueprint $table) {
            $table->id();
            $table->string('position_code')->unique();
            $table->string('business_line')->index();
            $table->string('country_code', 8)->default('ID');
            $table->string('title');
            $table->string('grade_band');
            $table->string('parent_position_code')->nullable()->index();
            $table->integer('budgeted_headcount')->default(1);
            $table->integer('current_headcount')->default(0);
            $table->boolean('is_critical')->default(false);
            $table->string('status')->default('ACTIVE'); // ACTIVE, FROZEN, RETIRED
            $table->timestamps();
        });

        Schema::create('hcm_headcount_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_code')->unique();
            $table->string('position_code')->index();
            $table->integer('requested_count')->default(1);
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED, FROZEN_HELD, FULFILLED
            $table->string('frozen_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('hcm_succession_plans', function (Blueprint $table) {
            $table->id();
            $table->string('position_code')->index();
            $table->string('successor_golden_id')->index();
            $table->string('readiness_level'); // READY_NOW, READY_1_YEAR, READY_2_YEARS
            $table->string('development_plan_status')->default('ACTIVE'); // ACTIVE, COMPLETED
            $table->timestamps();
        });

        Schema::create('hcm_workforce_demands', function (Blueprint $table) {
            $table->id();
            $table->string('planning_period')->index();
            $table->string('function_name');
            $table->integer('demand_fte');
            $table->integer('supply_internal_fte');
            $table->integer('gap_fte');
            $table->string('fulfillment_strategy'); // BUILD, BUY, BORROW, GIG
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_workforce_demands');
        Schema::dropIfExists('hcm_succession_plans');
        Schema::dropIfExists('hcm_headcount_requisitions');
        Schema::dropIfExists('hcm_positions');
    }
};
