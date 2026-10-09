<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nature_credit_marketplace_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('project_type'); // MANGROVE_RESTORATION, REFORESTATION, PEATLAND
            $table->boolean('has_verified_additionality')->default(false); // 330.1 & 330.6 Additionality
            $table->boolean('community_consent_granted')->default(true); // 330.1 & 330.5
            $table->boolean('social_remediation_filed')->default(false); // 330.5 Edge case
            $table->decimal('gross_credit_proceeds_usd', 18, 2);
            $table->decimal('community_benefit_share_pct', 5, 2)->default(30.00); // 30% benefit share
            $table->decimal('disbursed_benefit_share_usd', 18, 2);
            $table->boolean('issuance_cleared')->default(false);
            $table->timestamps();
        });

        Schema::create('water_stewardship_performance_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('facility_code')->unique();
            $table->string('site_code');
            $table->decimal('metered_baseline_m3', 12, 2);
            $table->decimal('metered_actual_m3', 12, 2);
            $table->decimal('verified_water_savings_m3', 12, 2); // 330.2 & 330.4
            $table->decimal('performance_payment_usd', 15, 2);
            $table->boolean('is_independently_measured')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_stewardship_performance_facilities');
        Schema::dropIfExists('nature_credit_marketplace_projects');
    }
};
