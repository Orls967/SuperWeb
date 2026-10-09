<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 111.1 Brand Standard Audits & Compliance
        Schema::create('htl_brand_standard_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('audit_code', 32)->unique();
            $table->uuid('property_id');
            $table->date('audit_date');
            $table->integer('total_checklist_items')->default(200);
            $table->integer('passed_items_count');
            $table->decimal('compliance_score_percent', 5, 2);
            $table->string('simulated_star_grade', 16); // 3_STAR, 4_STAR, 5_STAR_LUXURY
            $table->string('listing_status', 32)->default('ACTIVE'); // ACTIVE, SUSPENDED, ACTION_PLAN_REQUIRED
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
        });

        // 111.2 Hotel Franchise & Management Contracts
        Schema::create('htl_franchise_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('contract_code', 32)->unique();
            $table->uuid('property_id');
            $table->string('contract_type', 32); // FRANCHISE, MANAGEMENT_CONTRACT
            $table->bigInteger('initial_franchise_fee_idr')->default(0);
            $table->decimal('royalty_percentage', 5, 2)->default(5.0); // e.g. 5% of monthly revenue
            $table->string('status', 32)->default('ACTIVE'); // ACTIVE, TERMINATED
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
        });

        // 111.4 Rate Parity Violations & OTA Penalties
        Schema::create('htl_rate_parity_violations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('violation_code', 32)->unique();
            $table->uuid('property_id');
            $table->string('ota_channel_name', 64);
            $table->bigInteger('direct_bar_rate_idr');
            $table->bigInteger('ota_undercut_rate_idr');
            $table->bigInteger('penalty_levy_idr');
            $table->string('status', 32)->default('PENALTY_ACCRUED'); // PENALTY_ACCRUED, SETTLED
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_rate_parity_violations');
        Schema::dropIfExists('htl_franchise_contracts');
        Schema::dropIfExists('htl_brand_standard_audits');
    }
};
