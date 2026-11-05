<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ins_full_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('name');
            $table->string('line_category'); // VEHICLE, PROPERTY, MARINE_CARGO, HEALTH, LIFE, LIABILITY, WEATHER_INDEX
            $table->unsignedInteger('base_rate_bps'); // basis points, e.g. 150 = 1.5%
            $table->unsignedBigInteger('min_premium_idr')->default(500000);
            $table->unsignedInteger('deductible_bps')->default(1000); // 10%
            $table->unsignedInteger('quota_share_pct')->default(40); // 40% ceded treaty
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ins_full_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number')->unique();
            $table->foreignId('product_id')->constrained('ins_full_products')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_party_ref')->index();
            $table->unsignedBigInteger('sum_insured_idr');
            $table->unsignedBigInteger('gross_premium_idr');
            $table->unsignedBigInteger('ceded_premium_idr');
            $table->unsignedBigInteger('retained_premium_idr');
            $table->date('starts_at');
            $table->date('expires_at');
            $table->string('status')->default('active'); // active, lapsed, expired, claimed
            $table->timestamps();
        });

        Schema::create('ins_full_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('ins_full_policies')->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->unsignedBigInteger('amount_idr');
            $table->string('payment_status')->default('pending'); // pending, paid, overdue
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ins_full_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique();
            $table->foreignId('policy_id')->constrained('ins_full_policies')->cascadeOnDelete();
            $table->date('incident_date');
            $table->text('incident_description');
            $table->unsignedBigInteger('claimed_amount_idr');
            $table->unsignedBigInteger('approved_amount_idr')->default(0);
            $table->unsignedBigInteger('subrogation_recovered_idr')->default(0);
            $table->unsignedBigInteger('reinsurance_recovered_idr')->default(0);
            $table->unsignedBigInteger('net_payout_idr')->default(0);
            $table->string('status')->default('registered'); // registered, surveyed, approved, rejected, paid
            $table->unsignedBigInteger('approved_by_user_ref')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });

        Schema::create('ins_actuarial_triangles', function (Blueprint $table) {
            $table->id();
            $table->string('line_category');
            $table->unsignedInteger('accident_year');
            $table->unsignedInteger('development_year');
            $table->unsignedBigInteger('cumulative_paid_idr');
            $table->timestamps();

            $table->unique(['line_category', 'accident_year', 'development_year'], 'ins_triangle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_actuarial_triangles');
        Schema::dropIfExists('ins_full_claims');
        Schema::dropIfExists('ins_full_schedules');
        Schema::dropIfExists('ins_full_policies');
        Schema::dropIfExists('ins_full_products');
    }
};
