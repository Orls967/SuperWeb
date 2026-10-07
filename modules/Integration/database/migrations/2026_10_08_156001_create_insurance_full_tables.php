<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 156.1: Products and policies
        Schema::create('ins_products_penuh', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('product_name');
            $table->string('line_category'); // VEHICLE, PROPERTY, MARINE_CARGO, HEALTH, LIFE, LIABILITY, WEATHER_INDEX
            $table->decimal('base_rate_pct', 6, 3)->default(1.500);
            $table->decimal('minimum_premium', 18, 2)->default(500000.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ins_policies_penuh', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number')->unique();
            $table->string('product_code');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('sum_insured', 18, 2);
            $table->decimal('premium_amount', 18, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('ACTIVE'); // ACTIVE, LAPSED, CLAIMED, EXPIRED
            $table->timestamps();
        });

        // 156.4: Premium schedules
        Schema::create('ins_premium_schedule', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_status')->default('PENDING'); // PENDING, PAID, OVERDUE
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 156.5: Claims workflow
        Schema::create('ins_claims_penuh', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique();
            $table->string('policy_number');
            $table->text('incident_description');
            $table->decimal('claimed_amount', 18, 2);
            $table->decimal('approved_amount', 18, 2)->default(0.00);
            $table->decimal('subrogation_recovered', 18, 2)->default(0.00);
            $table->string('status')->default('REGISTERED'); // REGISTERED, SURVEYED, APPROVED, REJECTED, PAID
            $table->string('ledger_payout_ref')->nullable();
            $table->timestamps();
        });

        // 156.3: Actuarial reserves
        Schema::create('ins_actuarial_reserves', function (Blueprint $table) {
            $table->id();
            $table->string('line_category');
            $table->decimal('outstanding_claims_reserve', 18, 2)->default(0.00);
            $table->decimal('ibnr_reserve', 18, 2)->default(0.00); // Incurred But Not Reported
            $table->decimal('total_reserve_held', 18, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_actuarial_reserves');
        Schema::dropIfExists('ins_claims_penuh');
        Schema::dropIfExists('ins_premium_schedule');
        Schema::dropIfExists('ins_policies_penuh');
        Schema::dropIfExists('ins_products_penuh');
    }
};
