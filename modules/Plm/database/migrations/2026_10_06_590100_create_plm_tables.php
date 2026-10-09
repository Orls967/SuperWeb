<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plm_projects')) {
            Schema::create('plm_projects', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 40)->unique();
                $table->string('name', 160);
                $table->string('stage', 30)->default('ideation'); // ideation, scoping, business_case, development, testing, commercial_launch
                $table->bigInteger('budget_rd_idr')->default(0);
                $table->decimal('projected_roi_percent', 5, 2)->default(0);
                $table->string('status', 30)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plm_engineering_boms')) {
            Schema::create('plm_engineering_boms', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('project_id');
                $table->string('bom_number', 40)->unique();
                $table->string('version', 20)->default('v1.0');
                $table->json('components'); // Array of part codes, quantities, and specs
                $table->string('status', 30)->default('draft'); // draft, under_review, released_to_mbom
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plm_change_orders')) {
            Schema::create('plm_change_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('eco_number', 40)->unique();
                $table->uuid('ebom_id');
                $table->string('title', 160);
                $table->text('reason');
                $table->bigInteger('cost_impact_idr')->default(0);
                $table->string('disposition', 30)->default('scrap'); // scrap, rework, run_out
                $table->string('prev_hash', 64)->nullable();
                $table->string('hash', 64);
                $table->string('status', 30)->default('pending_approval'); // pending_approval, approved, rejected
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plm_lab_notebooks')) {
            Schema::create('plm_lab_notebooks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('project_id');
                $table->string('experiment_code', 40)->unique();
                $table->string('title', 160);
                $table->text('formula_payload_encrypted');
                $table->string('stability_test_result', 40)->default('pending'); // pass, fail, pending
                $table->decimal('sensory_score', 3, 1)->default(0); // 1.0 - 9.0 hedonic scale
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plm_lab_notebooks');
        Schema::dropIfExists('plm_change_orders');
        Schema::dropIfExists('plm_engineering_boms');
        Schema::dropIfExists('plm_projects');
    }
};
