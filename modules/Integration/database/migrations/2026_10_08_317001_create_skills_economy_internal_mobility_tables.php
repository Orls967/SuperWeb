<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_talent_gigs', function (Blueprint $table) {
            $table->id();
            $table->string('gig_code')->unique();
            $table->string('project_name');
            $table->string('requesting_business_unit');
            $table->integer('required_capacity_hours_per_week');
            $table->decimal('internal_hourly_rate_usd', 8, 2);
            $table->decimal('total_project_fee_usd', 15, 2); // 317.3 & 317.4
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('internal_talent_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('assignment_code')->unique();
            $table->string('gig_code')->index();
            $table->string('employee_id')->index();
            $table->integer('employee_allocated_hours_per_week');
            $table->integer('max_weekly_capacity_cap')->default(40); // 317.4 & 317.6 Capacity guardrail
            $table->boolean('manager_approval_granted')->default(false); // 317.1 & 317.6
            $table->boolean('continuity_handover_plan_filed')->default(true); // 317.5 Edge case
            $table->boolean('is_converted_to_permanent')->default(false); // 317.2
            $table->boolean('headcount_approval_for_permanent')->default(false); // 317.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_talent_assignments');
        Schema::dropIfExists('internal_talent_gigs');
    }
};
