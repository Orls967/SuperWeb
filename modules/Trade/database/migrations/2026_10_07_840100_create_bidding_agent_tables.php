<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 84.1 AI Bidding agents configuration
        Schema::create('trd_bidding_agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_code', 32)->unique();
            $table->string('entity_code', 32);
            $table->string('commodity_code', 32);
            $table->bigInteger('min_price_floor_idr');
            $table->bigInteger('max_price_ceiling_idr');
            $table->decimal('target_margin_pct', 5, 2)->default(15.0);
            $table->decimal('max_risk_score', 4, 2)->default(0.40);
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();
        });

        // 84.1 & 84.2 Tender auctions monitored & AI evaluation runs
        Schema::create('trd_bid_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code', 32)->unique();
            $table->unsignedBigInteger('bidding_agent_id');
            $table->string('tender_reference_code', 64);
            $table->bigInteger('commodity_feed_price_idr');
            $table->bigInteger('estimated_logistics_cost_idr');
            $table->decimal('buyer_risk_score', 4, 2);
            $table->bigInteger('optimal_bid_price_idr');
            $table->json('clause_drafts'); // 84.3 draft commercial clauses
            $table->string('evaluation_hash', 64);
            $table->timestamps();

            $table->foreign('bidding_agent_id')->references('id')->on('trd_bidding_agents')->cascadeOnDelete();
        });

        // 84.1 & 84.4 Bid submissions with mandatory four-eyes approval
        Schema::create('trd_bid_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_code', 32)->unique();
            $table->unsignedBigInteger('bid_run_id');
            $table->bigInteger('submitted_bid_price_idr');
            $table->boolean('requires_four_eyes')->default(true);
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 32)->default('PENDING_APPROVAL'); // PENDING_APPROVAL, SUBMITTED, WON, LOST, REJECTED
            $table->string('created_contract_code', 64)->nullable(); // 84.5 Post-win contract
            $table->timestamps();

            $table->foreign('bid_run_id')->references('id')->on('trd_bid_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trd_bid_submissions');
        Schema::dropIfExists('trd_bid_runs');
        Schema::dropIfExists('trd_bidding_agents');
    }
};
