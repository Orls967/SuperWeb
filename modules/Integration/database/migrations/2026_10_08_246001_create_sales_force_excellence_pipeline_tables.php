<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotas_territories', function (Blueprint $table) {
            $table->id();
            $table->string('territory_code')->unique();
            $table->string('territory_region');
            $table->string('assigned_rep_id');
            $table->decimal('quota_target_usd', 15, 2);
            $table->integer('fiscal_year')->default(2026);
            $table->timestamps();
        });

        Schema::create('sales_pipeline_deals', function (Blueprint $table) {
            $table->id();
            $table->string('deal_code')->unique();
            $table->string('business_line')->index();
            $table->string('client_name');
            $table->string('deal_stage'); // QUALIFICATION, SOLUTION_DESIGN, PROPOSAL_PRESENTED, NEGOTIATION, CLOSED_WON, CLOSED_LOST
            $table->boolean('stage_exit_criteria_met')->default(false);
            $table->decimal('deal_value_usd', 15, 2);
            $table->decimal('rep_confidence_pct', 5, 2);
            $table->decimal('independent_model_win_prob_pct', 5, 2); // 246.6 Edge case
            $table->decimal('weighted_forecast_usd', 15, 2);
            $table->unsignedBigInteger('territory_id');
            $table->timestamps();
        });

        Schema::create('sales_territory_disputes', function (Blueprint $table) {
            $table->id();
            $table->string('dispute_code')->unique();
            $table->unsignedBigInteger('deal_id');
            $table->string('claiming_rep_a');
            $table->string('claiming_rep_b');
            $table->string('dispute_rule_applied'); // ACCOUNT_HQ_ORIGIN, CONTRACT_SIGNING_LOCATION, REVENUE_SPLIT_50_50
            $table->text('formal_decision_notes');
            $table->string('arbitrated_by');
            $table->string('status')->default('OPEN'); // OPEN, RESOLVED
            $table->timestamps();
        });

        Schema::create('sales_win_loss_analyses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id')->unique();
            $table->string('outcome'); // WON, LOST
            $table->string('primary_decision_factor'); // PRICING, TECHNICAL_FIT, RELATIONSHIP, SLA_TERMS, COMPETITOR
            $table->string('competitor_name')->nullable();
            $table->decimal('discount_pct_approved', 5, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_win_loss_analyses');
        Schema::dropIfExists('sales_territory_disputes');
        Schema::dropIfExists('sales_pipeline_deals');
        Schema::dropIfExists('sales_quotas_territories');
    }
};
