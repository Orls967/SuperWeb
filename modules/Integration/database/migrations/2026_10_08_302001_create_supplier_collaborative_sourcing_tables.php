<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_co_development_programs', function (Blueprint $table) {
            $table->id();
            $table->string('program_code')->unique();
            $table->string('supplier_id')->index();
            $table->string('design_brief_topic');
            $table->decimal('joint_target_cost_usd', 15, 2);
            $table->boolean('cost_structure_disclosed')->default(true); // 302.2 & 302.5
            $table->decimal('estimated_cost_risk_penalty_usd', 15, 2)->default(0.00); // 302.5
            $table->decimal('shared_savings_ratio_pct', 5, 2)->default(50.00); // 50/50 fair split
            $table->decimal('supplier_innovation_score', 4, 2)->default(8.50);
            $table->timestamps();
        });

        Schema::create('strategic_sourcing_reverse_auctions', function (Blueprint $table) {
            $table->id();
            $table->string('auction_code')->unique();
            $table->string('commodity_lot_name');
            $table->decimal('floor_price_usd', 15, 2); // 302.6 Floor price guard
            $table->boolean('sealed_bids_unopened_prior_to_event')->default(true); // 302.3 & 302.4
            $table->string('awarded_supplier_id')->nullable();
            $table->decimal('winning_tco_score', 8, 2)->nullable(); // 302.3 TCO = price + risk + logistics + quality
            $table->text('award_justification_rationale')->nullable(); // 302.7
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_sourcing_reverse_auctions');
        Schema::dropIfExists('supplier_co_development_programs');
    }
};
