<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 158.1: Embedded insurance products catalog
        Schema::create('ins_embedded_products', function (Blueprint $table) {
            $table->id();
            $table->string('embed_code')->unique();
            $table->string('line_code', 10); // HTL, VEN, LOG, AGR, MIN, EPC, MAL
            $table->string('product_name');
            $table->decimal('premium_fee', 18, 2);
            $table->decimal('coverage_amount', 18, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 158.2: Parametric triggers & payouts
        Schema::create('ins_parametric_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_code')->unique();
            $table->string('trigger_type'); // RAINFALL_DROUGHT, FLIGHT_CANCEL, EVENT_CANCEL
            $table->decimal('threshold_value', 10, 2);
            $table->decimal('actual_value', 10, 2);
            $table->boolean('is_triggered')->default(false);
            $table->decimal('payout_per_policy', 18, 2);
            $table->timestamps();
        });

        // 158.3: Broker and agent marketplace with commissions
        Schema::create('ins_broker_commissions', function (Blueprint $table) {
            $table->id();
            $table->string('broker_code');
            $table->string('policy_number');
            $table->decimal('gross_premium', 18, 2);
            $table->decimal('commission_rate_pct', 5, 2)->default(10.00);
            $table->decimal('commission_amount', 18, 2);
            $table->string('status')->default('PAID');
            $table->timestamps();
        });

        // 158.5: Fraud detection scoring & SIU hold
        Schema::create('ins_fraud_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique();
            $table->integer('fraud_risk_score')->default(0); // 0 - 100
            $table->boolean('is_held_for_siu')->default(false);
            $table->string('decision')->default('APPROVED'); // APPROVED, SIU_INVESTIGATION, DENIED
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_fraud_assessments');
        Schema::dropIfExists('ins_broker_commissions');
        Schema::dropIfExists('ins_parametric_triggers');
        Schema::dropIfExists('ins_embedded_products');
    }
};
