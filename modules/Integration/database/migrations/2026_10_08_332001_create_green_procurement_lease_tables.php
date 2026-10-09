<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('green_procurement_rfq_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('evaluation_code')->unique();
            $table->string('rfq_code')->index();
            $table->string('supplier_id');
            $table->boolean('passed_compliance_gate')->default(false); // 332.1 Minimum compliance gate
            $table->decimal('carbon_efficiency_score', 4, 1);
            $table->decimal('circularity_score', 4, 1);
            $table->decimal('weighted_green_score', 4, 1); // 332.1 & 332.4
            $table->boolean('is_awarded')->default(false);
            $table->timestamps();
        });

        Schema::create('green_lease_performance_incentives', function (Blueprint $table) {
            $table->id();
            $table->string('lease_code')->unique();
            $table->string('tenant_id');
            $table->string('facility_code');
            $table->boolean('tenant_accepted_green_target')->default(true); // 332.2 & 332.5 Edge case
            $table->string('service_tier')->default('STANDARD_GREEN_TIER'); // 332.5 Alternate service tier if refused
            $table->decimal('verified_energy_reduction_pct', 5, 2);
            $table->decimal('incentive_rebate_usd', 15, 2);
            $table->boolean('performance_verified')->default(true); // 332.4
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('green_lease_performance_incentives');
        Schema::dropIfExists('green_procurement_rfq_evaluations');
    }
};
