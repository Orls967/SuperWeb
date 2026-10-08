<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_burnout_risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->decimal('overtime_hours_month', 5, 2);
            $table->decimal('utilization_rate_pct', 5, 2); // e.g. 115.00%
            $table->string('burnout_indicator'); // GREEN, YELLOW, RED (320.1 & 320.5)
            $table->boolean('mandatory_break_and_redistribution_enforced')->default(false); // 320.5 Edge case
            $table->timestamps();
        });

        Schema::create('workforce_safety_culture_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('site_code')->unique();
            $table->integer('near_miss_reports_count');
            $table->integer('stop_work_authority_uses_count');
            $table->decimal('safety_culture_leading_index', 4, 1); // 0 - 100 (320.3 & 320.4)
            $table->decimal('min_safety_target_index', 4, 1)->default(75.0);
            $table->boolean('safety_standard_met')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_safety_culture_metrics');
        Schema::dropIfExists('workforce_burnout_risk_profiles');
    }
};
