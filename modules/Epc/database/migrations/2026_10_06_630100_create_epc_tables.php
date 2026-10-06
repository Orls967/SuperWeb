<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epc_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_code')->unique();
            $table->string('project_name');
            $table->string('project_type'); // mall_extension, plant_construction, warehouse_expansion
            $table->string('client_entity_id');
            $table->bigInteger('total_rab_budget_idr');
            $table->decimal('target_physical_progress_pct', 5, 2)->default(0.00);
            $table->decimal('actual_physical_progress_pct', 5, 2)->default(0.00);
            $table->bigInteger('accumulated_cip_cost_idr')->default(0);
            $table->bigInteger('capitalized_asset_value_idr')->default(0);
            $table->date('start_date');
            $table->date('target_completion_date');
            $table->string('status')->default('in_progress'); // planned, in_progress, bast_issued, capitalized, closed
            $table->timestamps();
        });

        Schema::create('epc_wbs_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('epc_projects')->cascadeOnDelete();
            $table->string('wbs_code'); // WBS-1.1, WBS-1.2
            $table->string('task_name');
            $table->string('work_package'); // civil_structure, mep, architecture, finishing
            $table->decimal('weight_percentage', 5, 2); // weight in project curve-S
            $table->bigInteger('budget_allocation_idr');
            $table->bigInteger('actual_cost_incurred_idr')->default(0);
            $table->decimal('completion_percentage', 5, 2)->default(0.00);
            $table->string('status')->default('pending'); // pending, ongoing, completed
            $table->timestamps();

            $table->unique(['project_id', 'wbs_code']);
        });

        Schema::create('epc_progress_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('epc_projects')->cascadeOnDelete();
            $table->string('certificate_number')->unique(); // MC-01, MC-02
            $table->integer('period_month');
            $table->integer('period_year');
            $table->decimal('certified_cumulative_progress_pct', 5, 2);
            $table->decimal('certified_incremental_progress_pct', 5, 2);
            $table->bigInteger('gross_claim_amount_idr');
            $table->bigInteger('retention_deduction_idr')->default(0); // 5% retention
            $table->bigInteger('net_payable_amount_idr');
            $table->string('supervising_consultant_name');
            $table->dateTime('certified_at');
            $table->string('status')->default('approved'); // submitted, approved, paid
            $table->timestamps();
        });

        Schema::create('epc_cip_capitalizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('epc_projects')->cascadeOnDelete();
            $table->string('bast_number')->unique(); // BAST-1, BAST-FINAL
            $table->string('bast_type'); // BAST_1_PARTIAL, BAST_FINAL
            $table->bigInteger('total_cip_cost_idr');
            $table->string('target_asset_category'); // BUILDING, INFRASTRUCTURE, MACHINERY_PLANT
            $table->string('created_asset_id')->nullable();
            $table->dateTime('capitalized_at');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epc_cip_capitalizations');
        Schema::dropIfExists('epc_progress_certificates');
        Schema::dropIfExists('epc_wbs_nodes');
        Schema::dropIfExists('epc_projects');
    }
};
