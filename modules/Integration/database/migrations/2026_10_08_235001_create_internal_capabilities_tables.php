<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppm_internal_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('capability_code')->unique();
            $table->string('name');
            $table->string('category'); // PAYMENT, IDENTITY, LOGISTICS, DATA, AI, LOYALTY
            $table->string('version', 16);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('sla_availability_pct', 5, 2)->default(99.90);
            $table->integer('latency_target_ms')->default(100);
            $table->string('status')->default('ACTIVE'); // ACTIVE, DEPRECATED, RETIRED
            $table->date('deprecation_grace_period_end')->nullable();
            $table->timestamps();
        });

        Schema::create('ppm_capability_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_code')->unique();
            $table->string('capability_code')->index();
            $table->string('consumer_business_line')->index();
            $table->integer('rate_limit_rpm')->default(1000);
            $table->boolean('is_official_platform')->default(true);
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('ppm_internal_chargeback_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('entry_code')->unique();
            $table->string('capability_code')->index();
            $table->string('provider_unit')->default('PLATFORM_CORE');
            $table->string('consumer_unit')->index();
            $table->integer('usage_volume');
            $table->decimal('charge_amount', 15, 2);
            $table->decimal('credit_penalty_amount', 15, 2)->default(0);
            $table->decimal('net_transferred_amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('ppm_capability_sla_breaches', function (Blueprint $table) {
            $table->id();
            $table->string('breach_code')->unique();
            $table->string('capability_code')->index();
            $table->string('consumer_unit')->index();
            $table->decimal('actual_availability_pct', 5, 2);
            $table->decimal('target_availability_pct', 5, 2);
            $table->decimal('credit_penalty_amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('ppm_capability_duplication_detections', function (Blueprint $table) {
            $table->id();
            $table->string('detection_code')->unique();
            $table->string('business_line')->index();
            $table->string('bypassed_capability_code')->index();
            $table->string('shadow_project_name');
            $table->string('enforcement_action')->default('PREFER_PLATFORM_POLICY_ENFORCED'); // 235.6 edge case
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppm_capability_duplication_detections');
        Schema::dropIfExists('ppm_capability_sla_breaches');
        Schema::dropIfExists('ppm_internal_chargeback_ledgers');
        Schema::dropIfExists('ppm_capability_subscriptions');
        Schema::dropIfExists('ppm_internal_capabilities');
    }
};
