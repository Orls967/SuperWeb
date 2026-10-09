<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('int_engineering_dora_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('team_code');
            $table->string('period'); // e.g. 2026-M09
            $table->decimal('lead_time_hours', 8, 2);
            $table->decimal('deployment_frequency_per_day', 6, 2);
            $table->decimal('change_failure_rate_percentage', 5, 2);
            $table->decimal('mttr_minutes', 8, 2);
            $table->timestamps();
        });

        Schema::create('int_engineering_tech_debt_items', function (Blueprint $table) {
            $table->id();
            $table->string('debt_code')->unique();
            $table->string('module_code');
            $table->string('debt_type'); // code_complexity, flaky_test, legacy_adapter (471.3, 471.5)
            $table->date('paydown_due_date');
            $table->boolean('is_quarantined')->default(false); // 471.5 flaky test quarantine
            $table->boolean('paydown_budget_allocated')->default(false); // 471.6 risk
            $table->string('status')->default('active'); // active, paid_down
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('int_engineering_tech_debt_items');
        Schema::dropIfExists('int_engineering_dora_metrics');
    }
};
