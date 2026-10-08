<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code')->unique();
            $table->string('business_line')->index();
            $table->string('pricing_model'); // COST_PLUS, VALUE_BASED, DYNAMIC, CONTRACT, PROMO, PUBLIC_TARIFF
            $table->decimal('price_floor', 15, 2);
            $table->decimal('price_ceiling', 15, 2);
            $table->decimal('min_margin_pct', 5, 2)->default(15.00);
            $table->decimal('circuit_breaker_drop_pct', 5, 2)->default(25.00); // 245.6
            $table->timestamps();
        });

        Schema::create('pricing_change_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_code')->unique();
            $table->unsignedBigInteger('policy_rule_id');
            $table->decimal('proposed_price', 15, 2);
            $table->decimal('previous_price', 15, 2);
            $table->boolean('is_circuit_breaker_tripped')->default(false); // 245.6
            $table->boolean('is_mass_change')->default(false); // 245.7
            $table->integer('notice_days')->default(0); // 245.7
            $table->string('status')->default('PENDING'); // PENDING, APPROVED, TRIPPED, REJECTED
            $table->string('approved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pricing_elasticity_curves', function (Blueprint $table) {
            $table->id();
            $table->string('curve_code')->unique();
            $table->string('customer_segment'); // ENTERPRISE, SMB, CONSUMER
            $table->decimal('elasticity_coefficient', 6, 3);
            $table->decimal('optimal_price', 15, 2);
            $table->decimal('projected_revenue_lift_pct', 5, 2);
            $table->timestamps();
        });

        Schema::create('pricing_profit_pools', function (Blueprint $table) {
            $table->id();
            $table->string('business_line')->index();
            $table->string('segment');
            $table->string('channel'); // DIRECT, AGENT, ONLINE
            $table->decimal('gross_revenue', 15, 2);
            $table->decimal('cogs_cost', 15, 2);
            $table->decimal('net_profit', 15, 2);
            $table->string('strategic_posture'); // GROW, HOLD, HARVEST (245.4)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_profit_pools');
        Schema::dropIfExists('pricing_elasticity_curves');
        Schema::dropIfExists('pricing_change_proposals');
        Schema::dropIfExists('pricing_policy_rules');
    }
};
