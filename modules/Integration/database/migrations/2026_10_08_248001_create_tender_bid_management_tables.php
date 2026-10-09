<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_bids', function (Blueprint $table) {
            $table->id();
            $table->string('tender_code')->unique();
            $table->string('client_agency');
            $table->string('business_line')->index();
            $table->decimal('contract_estimate_usd', 15, 2);
            $table->decimal('qualification_score', 5, 2);
            $table->string('qualification_verdict'); // BID, NO_BID
            $table->boolean('coi_screened')->default(false); // 248.7
            $table->boolean('coi_cleared')->default(true);
            $table->string('human_approval_status')->default('PENDING'); // PENDING, APPROVED, REJECTED
            $table->string('status')->default('DRAFT'); // DRAFT, SUBMITTED, CANCELLED_BY_CLIENT, WON, LOST
            $table->timestamps();
        });

        Schema::create('tender_bid_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tender_id');
            $table->string('cost_category'); // ENGINEERING, LEGAL, RESEARCH
            $table->decimal('amount_usd', 12, 2);
            $table->string('accounting_treatment'); // EXPENSE, CAPITALIZE
            $table->timestamps();
        });

        Schema::create('tender_post_award_mobilizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tender_id')->unique();
            $table->string('project_code')->unique();
            $table->decimal('first_90_days_checklist_pct', 5, 2);
            $table->string('project_health_index'); // HEALTHY, CAUTION, AT_RISK
            $table->boolean('mobilization_cleared')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_post_award_mobilizations');
        Schema::dropIfExists('tender_bid_costs');
        Schema::dropIfExists('tender_bids');
    }
};
