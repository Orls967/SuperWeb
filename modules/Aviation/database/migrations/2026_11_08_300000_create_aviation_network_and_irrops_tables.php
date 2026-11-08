<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Versi Jadwal Penerbangan Musiman (IATA Season)
        Schema::create('avi_schedule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('airline_id')->constrained('avi_operators')->cascadeOnDelete();
            $table->string('season_code', 10); // S26, W26
            $table->integer('version_number');
            $table->string('status', 30)->default('draft');
            $table->date('effective_from');
            $table->date('effective_until');
            $table->dateTime('published_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['airline_id', 'season_code', 'version_number'], 'avi_sched_ver_unique');
            $table->index(['status', 'season_code']);
        });

        // 2. Perjanjian Kerjasama Codeshare Antar-Maskapai
        Schema::create('avi_codeshare_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operating_airline_id')->constrained('avi_operators')->cascadeOnDelete();
            $table->foreignId('marketing_airline_id')->constrained('avi_operators')->cascadeOnDelete();
            $table->string('agreement_code', 50)->unique();
            $table->string('agreement_type', 30); // free_sale, block_space_hard, block_space_soft
            $table->integer('revenue_split_bps')->default(8500); // 85% operating, 15% marketing
            $table->string('proration_rule', 30)->default('straight_rate');
            $table->string('status', 30)->default('proposed');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->timestamps();

            $table->index(['status', 'valid_from', 'valid_until']);
        });

        // 3. Pemetaan Nomor Penerbangan Mitra Codeshare
        Schema::create('avi_codeshare_flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('codeshare_agreement_id')->constrained('avi_codeshare_agreements')->cascadeOnDelete();
            $table->foreignId('operating_flight_id')->constrained('avi_flights')->cascadeOnDelete();
            $table->string('marketing_flight_number', 20);
            $table->integer('allotted_seats')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['codeshare_agreement_id', 'operating_flight_id', 'marketing_flight_number'], 'avi_cs_flight_unique');
            $table->index('marketing_flight_number');
        });

        // 4. Kasus Insiden Disrupsi Operasional (IRROPS)
        Schema::create('avi_disruption_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->constrained('avi_flights')->cascadeOnDelete();
            $table->string('case_number', 50)->unique();
            $table->string('incident_category', 50); // AOG_TECHNICAL, FORCE_MAJEURE_WEATHER, etc.
            $table->integer('delay_minutes')->default(0);
            $table->string('status', 30)->default('detected');
            $table->string('regulatory_category', 20)->default('CAT1');
            $table->boolean('duty_of_care_required')->default(true);
            $table->boolean('cash_compensation_required')->default(false);
            $table->boolean('hotel_required')->default(false);
            $table->text('incident_description');
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'regulatory_category']);
            $table->index('detected_at');
        });

        // 5. Manifes Penumpang Terdampak Disrupsi
        Schema::create('avi_disruption_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disruption_case_id')->constrained('avi_disruption_cases')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('avi_tickets')->cascadeOnDelete();
            $table->string('passenger_name', 150);
            $table->integer('priority_score')->default(0);
            $table->string('loyalty_tier', 20)->default('classic');
            $table->string('special_service_request', 30)->nullable();
            $table->string('reaccommodation_status', 30)->default('pending');
            $table->foreignId('new_flight_id')->nullable()->constrained('avi_flights')->nullOnDelete();
            $table->string('new_seat_number', 10)->nullable();
            $table->string('hotel_reservation_ref', 100)->nullable();
            $table->timestamps();

            $table->index(['disruption_case_id', 'priority_score'], 'avi_disrupt_pax_priority_idx');
            $table->index('reaccommodation_status');
        });

        // 6. E-Voucher Hak Penumpang PM 89/2015
        Schema::create('avi_care_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disruption_passenger_id')->constrained('avi_disruption_passengers')->cascadeOnDelete();
            $table->string('voucher_code', 64)->unique();
            $table->string('voucher_type', 30); // refreshment, snack, meal, cash_compensation, hotel_stay
            $table->bigInteger('nominal_amount_minor_unit')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->boolean('is_redeemed')->default(false);
            $table->dateTime('redeemed_at')->nullable();
            $table->string('merchant_code', 100)->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['voucher_type', 'is_redeemed']);
            $table->index('expires_at');
        });

        // 7. Akun Frequent Flyer Penumpang (Loyalti Aviasi)
        Schema::create('avi_loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('airline_id')->constrained('avi_operators')->cascadeOnDelete();
            $table->string('passenger_party_ref', 64)->index();
            $table->string('membership_number', 30)->unique();
            $table->string('current_tier', 20)->default('classic');
            $table->integer('tier_miles')->default(0);
            $table->integer('redeemable_miles')->default(0);
            $table->string('status', 20)->default('active');
            $table->date('tier_expiry_date');
            $table->timestamps();

            $table->index(['airline_id', 'current_tier']);
        });

        // 8. Buku Besar Subledger Mutasi Poin Miles
        Schema::create('avi_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loyalty_account_id')->constrained('avi_loyalty_accounts')->cascadeOnDelete();
            $table->string('transaction_reference', 64)->unique();
            $table->string('transaction_type', 40);
            $table->integer('miles_delta');
            $table->integer('tier_miles_delta')->default(0);
            $table->integer('running_balance');
            $table->foreignId('flight_ticket_id')->nullable()->constrained('avi_tickets')->nullOnDelete();
            $table->string('nonce_claim_hash', 64)->unique(); // Anti-double dipping
            $table->timestamp('created_at')->useCurrent();

            $table->index(['loyalty_account_id', 'transaction_type'], 'avi_loyalty_tx_type_idx');
        });

        // 9. Alokasi Ember Tarif Dinamis (Yield Buckets)
        Schema::create('avi_yield_buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->constrained('avi_flights')->cascadeOnDelete();
            $table->string('bucket_code', 5);
            $table->string('cabin_class', 20)->default('economy');
            $table->bigInteger('base_price_minor_unit');
            $table->integer('allocated_capacity');
            $table->integer('sold_count')->default(0);
            $table->integer('protection_level')->default(0);
            $table->integer('booking_limit')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->string('algorithm_certificate_hash', 64);
            $table->timestamps();

            $table->unique(['flight_id', 'bucket_code'], 'avi_yield_flight_bucket_unique');
            $table->index(['flight_id', 'is_closed']);
        });

        // 10. Pencatatan Klaim Asuransi Parametrik Otomatis
        Schema::create('avi_parametric_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disruption_case_id')->constrained('avi_disruption_cases')->cascadeOnDelete();
            $table->string('policy_number', 100);
            $table->string('passenger_party_ref', 64)->index();
            $table->string('trigger_type', 50);
            $table->bigInteger('payout_amount_minor_unit');
            $table->string('claim_status', 30)->default('pending');
            $table->string('external_reference', 100)->nullable();
            $table->timestamps();

            $table->index(['disruption_case_id', 'claim_status']);
            $table->index('policy_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avi_parametric_claims');
        Schema::dropIfExists('avi_yield_buckets');
        Schema::dropIfExists('avi_loyalty_transactions');
        Schema::dropIfExists('avi_loyalty_accounts');
        Schema::dropIfExists('avi_care_vouchers');
        Schema::dropIfExists('avi_disruption_passengers');
        Schema::dropIfExists('avi_disruption_cases');
        Schema::dropIfExists('avi_codeshare_flights');
        Schema::dropIfExists('avi_codeshare_agreements');
        Schema::dropIfExists('avi_schedule_versions');
    }
};
