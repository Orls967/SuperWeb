<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_dmaic_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('project_title');
            $table->string('dmaic_stage')->default('DEFINE'); // DEFINE, MEASURE, ANALYZE, IMPROVE, CONTROL
            $table->string('owner_lead_id');
            $table->decimal('baseline_metric_value', 15, 2);
            $table->decimal('target_metric_value', 15, 2);
            $table->decimal('claimed_financial_savings_usd', 15, 2)->default(0.00);
            $table->decimal('verified_financial_savings_usd', 15, 2)->default(0.00); // 276.4 & 276.6
            $table->boolean('is_finance_verified')->default(false); // 276.6
            $table->string('finance_auditor_id')->nullable();
            $table->timestamps();
        });

        Schema::create('operations_kpi_trees', function (Blueprint $table) {
            $table->id();
            $table->string('kpi_code')->unique();
            $table->string('domain_line')->index();
            $table->string('kpi_name');
            $table->decimal('current_value', 15, 2);
            $table->decimal('target_value', 15, 2);
            $table->string('status_color')->default('GREEN'); // GREEN, YELLOW, RED
            $table->decimal('customer_complaint_rate_pct', 5, 2)->default(0.00); // 276.7 counter-metric
            $table->boolean('is_gaming_flagged')->default(false); // 276.7
            $table->timestamps();
        });

        Schema::create('operations_standard_work_library', function (Blueprint $table) {
            $table->id();
            $table->string('standard_code')->unique();
            $table->string('title');
            $table->string('playbook_version')->default('V1.0');
            $table->integer('adopting_sites_count')->default(0);
            $table->timestamps();
        });

        Schema::create('operations_site_standard_variances', function (Blueprint $table) {
            $table->id();
            $table->string('variance_code')->unique();
            $table->string('standard_code')->index();
            $table->string('site_code');
            $table->text('variance_justification'); // 276.5
            $table->boolean('is_variance_approved')->default(false); // 276.5
            $table->string('approved_by_ops_head')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_site_standard_variances');
        Schema::dropIfExists('operations_standard_work_library');
        Schema::dropIfExists('operations_kpi_trees');
        Schema::dropIfExists('operations_dmaic_projects');
    }
};
