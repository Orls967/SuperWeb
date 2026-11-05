<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Katalog Embedded Products (ins_embed_products)
        Schema::create('ins_embed_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('line_domain', 32); // OTO, HTL, VEN, LGX, MALL, EPC, MINING, AGRI
            $table->string('name', 120);
            $table->string('coverage_type', 40); // CANCELLATION, DAMAGE, DELAY, ACCIDENT, PARAMETRIC, PERFORMANCE_BOND
            $table->unsignedBigInteger('default_sum_insured_idr');
            $table->unsignedInteger('premium_rate_bps'); // misal 150 bps = 1.5%
            $table->unsignedBigInteger('min_premium_idr')->default(10000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['line_domain', 'is_active']);
        });

        // 2. Polis Embedded (ins_embed_policies)
        Schema::create('ins_embed_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_number', 60)->unique();
            $table->foreignId('embedded_product_id')->constrained('ins_embed_products')->restrictOnDelete();
            $table->uuid('customer_party_id');
            $table->uuid('broker_party_id')->nullable();
            $table->string('context_type', 40); // booking, shipment, rental, loan, ticket, parcel
            $table->string('context_ref', 80);
            $table->unsignedBigInteger('sum_insured_idr');
            $table->unsignedBigInteger('premium_idr');
            $table->unsignedBigInteger('broker_commission_idr')->default(0);
            $table->string('status', 24)->default('active'); // active, claimed, expired, cancelled
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index('customer_party_id');
            $table->index('broker_party_id');
            $table->index(['context_type', 'context_ref']);
        });

        // 3. Konfigurasi Parametric Trigger (ins_param_triggers)
        Schema::create('ins_param_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_code', 60)->unique();
            $table->string('metric_name', 40); // NDVI, RAINFALL_DEFICIT, EARTHQUAKE_INTENSITY, FLIGHT_CANCEL
            $table->string('region_or_plot', 60);
            $table->integer('threshold_value'); // ambang batas
            $table->string('comparison_operator', 4); // <, <=, >, >=
            $table->unsignedInteger('payout_percentage_bps'); // persentase santunan dari sum insured
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['metric_name', 'region_or_plot']);
        });

        // 4. Rekaman Eksekusi Trigger Parametrik (ins_param_events)
        Schema::create('ins_param_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 120)->unique();
            $table->foreignId('trigger_id')->constrained('ins_param_triggers')->restrictOnDelete();
            $table->integer('observed_value');
            $table->json('oracle_payload');
            $table->string('oracle_signature', 128)->nullable();
            $table->unsignedInteger('policies_evaluated');
            $table->unsignedInteger('policies_paid');
            $table->unsignedBigInteger('total_payout_idr')->default(0);
            $table->timestamp('triggered_at');
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Profil Broker / Agen (ins_market_brokers)
        Schema::create('ins_market_brokers', function (Blueprint $table) {
            $table->id();
            $table->uuid('party_id');
            $table->string('license_number', 60)->unique();
            $table->string('tier', 20)->default('silver'); // bronze, silver, gold, platinum
            $table->unsignedInteger('commission_rate_bps')->default(1000); // 10%
            $table->unsignedSmallInteger('rating_score')->default(85); // 0 - 100
            $table->unsignedBigInteger('total_gross_written_idr')->default(0);
            $table->unsignedBigInteger('total_commission_earned_idr')->default(0);
            $table->string('status', 20)->default('active'); // active, suspended, terminated
            $table->timestamps();

            $table->unique('party_id');
        });

        // 6. Komisi Broker (ins_market_commissions)
        Schema::create('ins_market_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broker_id')->constrained('ins_market_brokers')->restrictOnDelete();
            $table->foreignId('embedded_policy_id')->constrained('ins_embed_policies')->restrictOnDelete();
            $table->unsignedBigInteger('premium_idr');
            $table->unsignedInteger('commission_bps');
            $table->unsignedBigInteger('commission_amount_idr');
            $table->string('status', 20)->default('pending'); // pending, settled, reversed
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('bank_ledger_transactions')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['broker_id', 'status']);
        });

        // 7. Fraud Assessment & Scoring (ins_fraud_scores)
        Schema::create('ins_fraud_scores', function (Blueprint $table) {
            $table->id();
            $table->string('assessment_key', 120)->unique();
            $table->string('target_type', 32); // claim, policy, customer
            $table->string('target_ref', 80);
            $table->unsignedSmallInteger('risk_score'); // 0 - 100
            $table->string('risk_level', 16); // low, medium, high, critical
            $table->boolean('ring_fraud_detected')->default(false);
            $table->json('ring_cluster_nodes')->nullable();
            $table->json('score_breakdown');
            $table->string('recommended_action', 32); // auto_approve, manual_review, siu_investigation, reject
            $table->timestamps();

            $table->index(['target_type', 'target_ref']);
            $table->index('risk_level');
        });

        // 8. Berkas Investigasi SIU (ins_siu_investigations)
        Schema::create('ins_siu_investigations', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 60)->unique();
            $table->foreignId('fraud_assessment_id')->constrained('ins_fraud_scores')->restrictOnDelete();
            $table->uuid('subject_party_id');
            $table->text('reason');
            $table->string('status', 24)->default('assigned'); // assigned, investigating, confirmed_fraud, cleared
            $table->text('investigator_findings')->nullable();
            $table->text('decision_notes')->nullable();
            $table->unsignedBigInteger('approval_ref')->nullable(); // four-eyes decision approval
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('subject_party_id');
            $table->index(['status', 'assigned_to']);
        });

        // 9. Blacklist Industri (ins_blacklisted_entities)
        Schema::create('ins_blacklisted_entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('party_id')->nullable();
            $table->string('identity_number', 64)->nullable()->index(); // NIK / Paspor / NPWP terenkripsi / hash
            $table->string('phone_number_hash', 64)->nullable()->index();
            $table->string('reason', 255);
            $table->string('source_case_number', 60);
            $table->timestamp('blacklisted_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ins_blacklisted_entities');
        Schema::dropIfExists('ins_siu_investigations');
        Schema::dropIfExists('ins_fraud_scores');
        Schema::dropIfExists('ins_market_commissions');
        Schema::dropIfExists('ins_market_brokers');
        Schema::dropIfExists('ins_param_events');
        Schema::dropIfExists('ins_param_triggers');
        Schema::dropIfExists('ins_embed_policies');
        Schema::dropIfExists('ins_embed_products');
    }
};
