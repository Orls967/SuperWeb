<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_carbon_shadow_capex_appraisals', function (Blueprint $table) {
            $table->id();
            $table->string('appraisal_code')->unique();
            $table->string('site_code');
            $table->decimal('nominal_capex_usd', 18, 2);
            $table->decimal('annual_carbon_intensity_tco2e', 12, 2);
            $table->decimal('internal_carbon_shadow_price_usd_per_ton', 8, 2); // 326.1
            $table->decimal('shadow_adjusted_npv_usd', 18, 2);
            $table->boolean('posted_to_actual_cash_ledger')->default(false); // 326.4 Shadow never posts as cash
            $table->timestamps();
        });

        Schema::create('sustainability_linked_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('instrument_code')->unique(); // e.g. SUKUK-GREEN-01
            $table->string('instrument_type'); // GREEN_LOAN, SUSTAINABILITY_LINKED_SUKUK
            $table->decimal('base_coupon_rate_pct', 5, 2); // e.g. 5.50%
            $table->decimal('target_emissions_reduction_pct', 5, 2);
            $table->decimal('realized_emissions_reduction_pct', 5, 2);
            $table->decimal('pricing_step_up_pct', 4, 2)->default(0.50); // +0.50% penalty if missed
            $table->decimal('effective_coupon_rate_pct', 5, 2);
            $table->boolean('has_evidenced_abatement_plan')->default(true); // 326.6 Anti-greenwashing
            $table->boolean('kpi_target_achieved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sustainability_linked_instruments');
        Schema::dropIfExists('internal_carbon_shadow_capex_appraisals');
    }
};
