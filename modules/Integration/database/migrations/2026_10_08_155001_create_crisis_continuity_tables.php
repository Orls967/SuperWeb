<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 155.1: Health surveillance risk map
        Schema::create('cr_health_surveillance_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone_code')->unique();
            $table->string('country_code', 2);
            $table->string('region_name');
            $table->string('risk_level')->default('LOW'); // LOW, MODERATE, HIGH, CRITICAL
            $table->string('mandated_restriction')->default('NONE'); // NONE, REDUCED_CAPACITY, FULL_LOCKDOWN
            $table->timestamps();
        });

        // 155.2: Business Interruption (BI) crisis insurance claims
        Schema::create('cr_business_interruption_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_code')->unique();
            $table->string('entity_code');
            $table->string('line_code', 10);
            $table->string('declaration_event_code');
            $table->decimal('calculated_interruption_loss', 18, 2);
            $table->decimal('insurance_payout_approved', 18, 2);
            $table->string('status')->default('PAID');
            $table->string('ledger_reference')->nullable();
            $table->timestamps();
        });

        // 155.3: Workforce contingency & WFH tracking
        Schema::create('cr_workforce_contingencies', function (Blueprint $table) {
            $table->id();
            $table->string('role_code');
            $table->string('critical_function');
            $table->boolean('can_remote')->default(true);
            $table->string('backup_role_code');
            $table->boolean('is_cross_trained')->default(true);
            $table->timestamps();
        });

        // 155.4: Force majeure activations
        Schema::create('cr_force_majeure_activations', function (Blueprint $table) {
            $table->id();
            $table->string('activation_code')->unique();
            $table->string('contract_code');
            $table->string('reason');
            $table->timestamp('activated_at');
            $table->timestamp('revoked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cr_force_majeure_activations');
        Schema::dropIfExists('cr_workforce_contingencies');
        Schema::dropIfExists('cr_business_interruption_claims');
        Schema::dropIfExists('cr_health_surveillance_zones');
    }
};
