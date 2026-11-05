<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Polis Asuransi Jiwa (ins_lh_life_policies)
        Schema::create('ins_lh_life_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 60)->unique();
            $table->uuid('insured_party_id');
            $table->string('plan_type', 32); // TERM_LIFE, WHOLE_LIFE, ENDOWMENT_SAVING
            $table->unsignedSmallInteger('entry_age');
            $table->unsignedSmallInteger('term_years');
            $table->unsignedBigInteger('sum_insured_idr');
            $table->unsignedBigInteger('annual_premium_idr');
            $table->unsignedBigInteger('saving_accumulated_idr')->default(0);
            $table->string('status', 24)->default('active'); // active, lapsed, matured, claimed, surrendered
            $table->date('issued_date');
            $table->date('maturity_date');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index('insured_party_id');
            $table->index(['plan_type', 'status']);
        });

        // 2. Penerima Manfaat Polis Jiwa (ins_lh_beneficiaries)
        Schema::create('ins_lh_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('life_policy_id')->constrained('ins_lh_life_policies')->cascadeOnDelete();
            $table->uuid('beneficiary_party_id');
            $table->string('relationship', 40); // SPOUSE, CHILD, PARENT, SIBLING, ESTATE
            $table->unsignedInteger('share_percentage_bps'); // Total 10000 bps = 100%
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->index('beneficiary_party_id');
            $table->index(['life_policy_id', 'is_primary']);
        });

        // 3. Polis Asuransi Kesehatan (ins_lh_health_policies)
        Schema::create('ins_lh_health_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 60)->unique();
            $table->uuid('member_party_id');
            $table->string('plan_tier', 24); // BASIC, SILVER, GOLD, PLATINUM
            $table->unsignedBigInteger('annual_limit_idr');
            $table->unsignedBigInteger('annual_utilized_idr')->default(0);
            $table->unsignedBigInteger('room_daily_limit_idr');
            $table->unsignedBigInteger('deductible_idr')->default(0);
            $table->string('status', 24)->default('active'); // active, suspended, terminated
            $table->date('effective_date');
            $table->date('expiry_date');
            $table->timestamps();

            $table->index('member_party_id');
            $table->index(['plan_tier', 'status']);
        });

        // 4. Surat Jaminan Cashless RS (ins_lh_guarantee_letters)
        Schema::create('ins_lh_guarantee_letters', function (Blueprint $table) {
            $table->id();
            $table->string('gl_number', 60)->unique();
            $table->foreignId('health_policy_id')->constrained('ins_lh_health_policies')->restrictOnDelete();
            $table->string('hospital_encounter_ref', 80);
            $table->uuid('hospital_party_id');
            $table->string('diagnosis_icd10', 20);
            $table->unsignedBigInteger('approved_ceiling_idr');
            $table->string('status', 24)->default('issued'); // issued, active_admission, settled, cancelled
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('valid_until');
            $table->timestamps();

            $table->index('hospital_party_id');
            $table->index('hospital_encounter_ref');
            $table->index(['health_policy_id', 'status']);
        });

        // 5. Klaim Kesehatan (ins_lh_health_claims)
        Schema::create('ins_lh_health_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 60)->unique();
            $table->foreignId('health_policy_id')->constrained('ins_lh_health_policies')->restrictOnDelete();
            $table->foreignId('guarantee_letter_id')->nullable()->constrained('ins_lh_guarantee_letters')->nullOnDelete();
            $table->string('claim_type', 20); // CASHLESS, REIMBURSEMENT
            $table->unsignedBigInteger('incurred_amount_idr');
            $table->unsignedBigInteger('approved_amount_idr')->default(0);
            $table->unsignedBigInteger('patient_share_idr')->default(0);
            $table->string('status', 24)->default('submitted'); // submitted, approved, settled, appealed, rejected
            $table->string('denial_code', 32)->nullable();
            $table->text('adjudication_notes')->nullable();
            $table->foreignId('settled_ledger_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['health_policy_id', 'status']);
        });

        // 6. Log Telemetri Wellness (ins_lh_wellness_logs)
        Schema::create('ins_lh_wellness_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_party_id');
            $table->date('log_date');
            $table->unsignedInteger('daily_steps');
            $table->unsignedSmallInteger('avg_heart_rate_bpm');
            $table->unsignedSmallInteger('sleep_minutes');
            $table->unsignedSmallInteger('health_score'); // 0 - 100
            $table->boolean('is_flagged_gaming')->default(false);
            $table->string('source_device_hash', 64);
            $table->timestamps();

            $table->index('user_party_id');
            $table->index(['user_party_id', 'log_date']);
        });

        // 7. Hadiah & Diskon Wellness (ins_lh_wellness_rewards)
        Schema::create('ins_lh_wellness_rewards', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_party_id');
            $table->string('reward_type', 32); // PREMIUM_DISCOUNT_BPS, LOYALTY_POINTS, VOUCHER
            $table->unsignedInteger('reward_value'); // basis poin diskon premi atau poin
            $table->string('badge_title', 60);
            $table->date('period_month');
            $table->boolean('is_applied')->default(false);
            $table->timestamps();

            $table->index('user_party_id');
            $table->index(['user_party_id', 'period_month']);
        });

        // 8. Dana Portofolio Unit Link (ins_lh_unit_link_funds)
        Schema::create('ins_lh_unit_link_funds', function (Blueprint $table) {
            $table->id();
            $table->string('fund_code', 32)->unique();
            $table->string('name', 100);
            $table->string('asset_class', 32); // EQUITY, BALANCED, FIXED_INCOME, MONEY_MARKET
            $table->unsignedBigInteger('current_nav_per_unit_idr'); // harga unit minor unit
            $table->unsignedBigInteger('total_aum_idr');
            $table->unsignedBigInteger('total_units');
            $table->unsignedSmallInteger('management_fee_bps')->default(150); // 1.5%
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['asset_class', 'is_active']);
        });

        // 9. Riwayat Harian NAV Unit Link (ins_lh_unit_link_nav_histories)
        Schema::create('ins_lh_unit_link_nav_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('ins_lh_unit_link_funds')->cascadeOnDelete();
            $table->date('valuation_date');
            $table->unsignedBigInteger('nav_per_unit_idr');
            $table->unsignedBigInteger('total_aum_idr');
            $table->unsignedBigInteger('total_units');
            $table->timestamps();

            $table->unique(['fund_id', 'valuation_date']);
        });

        // 10. Saldo Unit Pemegang Polis (ins_lh_unit_link_holdings)
        Schema::create('ins_lh_unit_link_holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('life_policy_id')->constrained('ins_lh_life_policies')->cascadeOnDelete();
            $table->foreignId('fund_id')->constrained('ins_lh_unit_link_funds')->restrictOnDelete();
            $table->unsignedBigInteger('units_held'); // jumlah unit
            $table->unsignedBigInteger('acquisition_cost_idr');
            $table->unsignedBigInteger('current_valuation_idr');
            $table->timestamps();

            $table->unique(['life_policy_id', 'fund_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_lh_unit_link_holdings');
        Schema::dropIfExists('ins_lh_unit_link_nav_histories');
        Schema::dropIfExists('ins_lh_unit_link_funds');
        Schema::dropIfExists('ins_lh_wellness_rewards');
        Schema::dropIfExists('ins_lh_wellness_logs');
        Schema::dropIfExists('ins_lh_health_claims');
        Schema::dropIfExists('ins_lh_guarantee_letters');
        Schema::dropIfExists('ins_lh_health_policies');
        Schema::dropIfExists('ins_lh_beneficiaries');
        Schema::dropIfExists('ins_lh_life_policies');
    }
};
