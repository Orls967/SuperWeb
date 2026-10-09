<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 201.1 & 201.2: Hierarchical forecasting registry with bottom-up / top-down consistency
        Schema::create('ai_forecast_registries', function (Blueprint $table) {
            $table->id();
            $table->string('forecast_code')->unique();
            $table->string('domain_code');
            $table->string('forecast_period'); // e.g. 2026-Q4
            $table->decimal('national_aggregate_units', 18, 2);
            $table->decimal('regional_sum_units', 18, 2);
            $table->decimal('reconciliation_discrepancy', 18, 2)->default(0.00); // Must be 0.00
            $table->decimal('backtest_mape_percent', 6, 2)->default(5.00);
            $table->timestamps();
        });

        // 201.3: Executive S&OP cross-line sign-offs
        Schema::create('ai_sop_executive_signoffs', function (Blueprint $table) {
            $table->id();
            $table->string('signoff_code')->unique();
            $table->string('forecast_period');
            $table->decimal('demand_review_units', 18, 2);
            $table->decimal('capacity_allocated_units', 18, 2);
            $table->string('signed_off_by');
            $table->string('status')->default('APPROVED');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sop_executive_signoffs');
        Schema::dropIfExists('ai_forecast_registries');
    }
};
