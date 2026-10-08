<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_unified_registers', function (Blueprint $table) {
            $table->id();
            $table->string('risk_code')->unique();
            $table->string('business_line')->index();
            $table->string('risk_title');
            $table->string('risk_category'); // MARKET_FX, COMMODITY, CREDIT, OPERATIONAL, COMPLIANCE
            $table->decimal('inherent_score', 5, 2);
            $table->decimal('residual_score', 5, 2);
            $table->decimal('kri_metric_value', 8, 2);
            $table->integer('incident_count')->default(0); // 253.6
            $table->timestamps();
        });

        Schema::create('risk_aggregate_correlations', function (Blueprint $table) {
            $table->id();
            $table->string('portfolio_code')->unique();
            $table->json('correlated_lines_json');
            $table->decimal('diversification_benefit_pct', 5, 2);
            $table->decimal('tail_risk_var_99_usd', 15, 2);
            $table->decimal('capital_buffer_required_usd', 15, 2);
            $table->timestamps();
        });

        Schema::create('risk_assurance_coverage_maps', function (Blueprint $table) {
            $table->id();
            $table->string('process_code')->unique();
            $table->string('process_name');
            $table->boolean('is_material_process')->default(true);
            $table->string('process_owner');
            $table->string('assurance_provider'); // INTERNAL_AUDIT, EXTERNAL_AUDIT, COMPLIANCE_TESTING, NONE
            $table->string('assurance_tester_id')->nullable();
            $table->boolean('is_self_reviewed')->default(false); // 253.7
            $table->boolean('has_coverage_gap')->default(false); // 253.5
            $table->boolean('remediation_plan_required')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assurance_coverage_maps');
        Schema::dropIfExists('risk_aggregate_correlations');
        Schema::dropIfExists('risk_unified_registers');
    }
};
