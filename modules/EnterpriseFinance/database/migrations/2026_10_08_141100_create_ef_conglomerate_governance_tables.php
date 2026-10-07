<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\database\migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 141.1 Holding & Subholding dividend distributions
        Schema::create('ef_subholding_dividends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('dividend_code')->unique();
            $table->string('subholding_name'); // OTOMOTIF, HOSPITALITY, RESOURCES, INFRASTRUCTURE, CONSUMER
            $table->string('subsidiary_entity_id');
            $table->bigInteger('verified_net_profit_minor');
            $table->double('dividend_payout_ratio_pct', 5, 2);
            $table->bigInteger('dividend_declared_minor');
            $table->string('fiscal_year');
            $table->string('status')->default('DECLARED'); // DECLARED, PAID
            $table->timestamps();

            $table->index(['subholding_name', 'fiscal_year']);
        });

        // 141.2 Capital Allocation Proposals & Capex Approvals
        Schema::create('ef_capex_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('proposal_code')->unique();
            $table->string('line_code'); // LINE_1 .. LINE_17
            $table->string('project_title');
            $table->bigInteger('requested_budget_minor');
            $table->bigInteger('allocated_treasury_budget_minor')->default(0);
            $table->bigInteger('actual_spent_minor')->default(0);
            $table->double('irr_pct', 5, 2);
            $table->bigInteger('npv_minor');
            $table->double('esg_score', 4, 2);
            $table->string('status')->default('SUBMITTED'); // SUBMITTED, APPROVED, REJECTED, COMPLETED
            $table->timestamps();

            $table->index(['line_code', 'status']);
        });

        // 141.3 M&A Due Diligence & Monolith Integration Backfill
        Schema::create('ef_ma_acquisitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('deal_code')->unique();
            $table->string('target_company_name');
            $table->string('target_industry');
            $table->bigInteger('valuation_minor');
            $table->bigInteger('deal_value_minor');
            $table->string('integration_playbook_status')->default('PENDING'); // PENDING, BACKFILLED, COMPLETED
            $table->json('backfilled_entity_ids')->nullable();
            $table->timestamps();

            $table->index(['deal_code', 'integration_playbook_status']);
        });

        // 141.5 17-Lines Segment Reporting & Consolidated Financials
        Schema::create('ef_segment_financial_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('report_code')->unique();
            $table->string('period_quarter'); // e.g. 2026-Q3
            $table->json('segment_contributions'); // 17 lines revenue & EBITDA breakdown
            $table->bigInteger('total_consolidated_revenue_minor');
            $table->bigInteger('total_consolidated_ebitda_minor');
            $table->timestamps();

            $table->index(['period_quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ef_segment_financial_reports');
        Schema::dropIfExists('ef_ma_acquisitions');
        Schema::dropIfExists('ef_capex_proposals');
        Schema::dropIfExists('ef_subholding_dividends');
    }
};
