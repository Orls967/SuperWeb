<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 160.1: Takaful window funds (dana tabarru' & wakalah fee)
        Schema::create('tak_funds', function (Blueprint $table) {
            $table->id();
            $table->string('fund_code')->unique();
            $table->string('fund_name');
            $table->decimal('total_contributions', 18, 2)->default(0.00);
            $table->decimal('wakalah_fee_rate_pct', 5, 2)->default(15.00); // 15% operator fee
            $table->decimal('wakalah_fees_collected', 18, 2)->default(0.00);
            $table->decimal('tabarru_pool_balance', 18, 2)->default(0.00); // Mutual assistance pool
            $table->decimal('claims_paid', 18, 2)->default(0.00);
            $table->decimal('surplus_distributed', 18, 2)->default(0.00);
            $table->timestamps();
        });

        // 160.2: Agricultural insurance parametric contracts
        Schema::create('tak_agri_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code')->unique();
            $table->string('farmer_id');
            $table->string('commodity_type'); // PALM_OIL, PADDY, CORN, RUBBER
            $table->decimal('insured_hectares', 8, 2);
            $table->decimal('sum_insured_per_ha', 18, 2);
            $table->decimal('ndvi_trigger_threshold', 5, 3)->default(0.350); // Vegetation index threshold
            $table->decimal('actual_ndvi_measured', 5, 3)->nullable();
            $table->decimal('payout_amount', 18, 2)->default(0.00);
            $table->string('status')->default('ACTIVE'); // ACTIVE, TRIGGERED, PAID
            $table->timestamps();
        });

        // 160.3: Micro-insurance policies
        Schema::create('tak_micro_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_code')->unique();
            $table->string('product_type'); // DAILY_DRIVER, TRIP, GADGET_WARRANTY
            $table->decimal('daily_premium', 18, 2)->default(2000.00); // Rp 2,000/day
            $table->decimal('max_payout', 18, 2)->default(5000000.00);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tak_micro_policies');
        Schema::dropIfExists('tak_agri_contracts');
        Schema::dropIfExists('tak_funds');
    }
};
