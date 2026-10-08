<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_unified_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code')->unique();
            $table->string('domain_line')->index();
            $table->string('planning_period'); // e.g. 2026-M11
            $table->integer('version')->default(1);
            $table->decimal('unified_demand_units', 15, 2);
            $table->decimal('capacity_hours_allocated', 15, 2);
            $table->decimal('workforce_headcount_planned', 15, 2);
            $table->decimal('financial_budget_usd', 15, 2);
            $table->boolean('is_signed_off')->default(false); // 277.5
            $table->boolean('is_archived')->default(false); // 277.5
            $table->timestamps();
        });

        Schema::create('operations_finite_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_code')->unique();
            $table->string('resource_id')->index(); // MACHINE, WORKER, CRANE, VESSEL
            $table->decimal('capacity_limit_hours', 8, 2);
            $table->decimal('booked_hours', 8, 2)->default(0.00); // 277.4 100% feasibility (no overbook)
            $table->date('schedule_date');
            $table->boolean('is_overbooked')->default(false);
            $table->timestamps();
        });

        Schema::create('operations_reschedule_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code')->unique();
            $table->string('schedule_code')->index();
            $table->string('trigger_reason'); // MACHINE_BREAKDOWN, WORKER_ABSENCE, PERMIT_DELAY
            $table->decimal('adjusted_hours', 8, 2);
            $table->boolean('hard_constraint_respected')->default(true); // 277.6
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_reschedule_events');
        Schema::dropIfExists('operations_finite_schedules');
        Schema::dropIfExists('operations_unified_plans');
    }
};
