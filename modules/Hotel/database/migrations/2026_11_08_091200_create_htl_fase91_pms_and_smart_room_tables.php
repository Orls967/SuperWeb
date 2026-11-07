<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Parameter Faktor Eksternal Berversi (G7-D43)
        if (!Schema::hasTable('htl_external_factors')) {
            Schema::create('htl_external_factors', function (Blueprint $table): void {
                $table->id();
                $table->string('factor_code', 64);
                $table->string('city', 64)->default('All');
                $table->json('value_json');
                $table->string('source_reference', 255)->default('UU HKPD / Perda');
                $table->string('label_classification', 32)->default('regulasi'); // regulasi, asumsi_simulasi, fixture
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->dateTime('effective_from');
                $table->dateTime('effective_to')->nullable();
                $table->timestamps();

                $table->index(['factor_code', 'city', 'is_active', 'version'], 'htl_ext_factors_lookup_idx');
            });
        }

        // 2. Kunci Digital Kamar Berbasis Sesi (D91-3)
        if (!Schema::hasTable('htl_digital_keys')) {
            Schema::create('htl_digital_keys', function (Blueprint $table): void {
                $table->id();
                $table->string('key_uuid', 36)->unique();
                $table->string('reservation_id', 64);
                $table->foreign('reservation_id')->references('id')->on('htl_reservations')->cascadeOnDelete();
                $table->string('room_id', 64);
                $table->foreign('room_id')->references('id')->on('htl_rooms')->cascadeOnDelete();
                $table->foreignId('guest_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('session_token_hash', 64);
                $table->string('qr_payload_encrypted', 512);
                $table->dateTime('valid_from');
                $table->dateTime('valid_until');
                $table->dateTime('first_used_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->string('status', 32)->default('pending_activation'); // 7 status SM DigitalKey
                $table->timestamps();

                $table->index(['reservation_id', 'status'], 'htl_digital_keys_res_status_idx');
            });
        }

        // 3. Antrean Kepatuhan Laporan Tamu WNA APOA UU 6/2011 (D91-3, T-8)
        if (!Schema::hasTable('htl_apoa_reports')) {
            Schema::create('htl_apoa_reports', function (Blueprint $table): void {
                $table->id();
                $table->string('reservation_id', 64);
                $table->foreign('reservation_id')->references('id')->on('htl_reservations')->cascadeOnDelete();
                $table->string('property_id', 64);
                $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
                $table->foreignId('guest_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('nationality_country_code', 3);
                $table->string('passport_blind_index', 64);
                $table->string('passport_encrypted', 512);
                $table->string('visa_type', 32)->nullable();
                $table->boolean('bali_pwa_levy_verified')->default(false);
                $table->string('apoa_submission_batch', 64)->nullable();
                $table->string('acknowledgement_reference', 64)->nullable();
                $table->dateTime('deadline_at');
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('acknowledged_at')->nullable();
                $table->string('status', 32)->default('pending'); // 7 status SM ApoaReport
                $table->timestamps();

                $table->index(['property_id', 'status', 'deadline_at'], 'htl_apoa_reports_prop_status_idx');
            });
        }

        // 4. Versi Rate Plan & Penetapan Tarif Harian (D91-4)
        if (!Schema::hasTable('htl_rate_plan_versions')) {
            Schema::create('htl_rate_plan_versions', function (Blueprint $table): void {
                $table->id();
                $table->string('property_id', 64);
                $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
                $table->string('room_type_id', 64);
                $table->foreign('room_type_id')->references('id')->on('htl_room_types')->cascadeOnDelete();
                $table->string('version_code', 32);
                $table->date('business_date');
                $table->bigInteger('base_rate_idr');
                $table->bigInteger('calculated_bar_rate_idr');
                $table->bigInteger('floor_rate_idr')->default(450000);
                $table->bigInteger('ceiling_rate_idr')->default(3000000);
                $table->unsignedInteger('occupancy_bps')->default(10000);
                $table->unsignedInteger('lead_bps')->default(10000);
                $table->unsignedInteger('event_bps')->default(10000);
                $table->boolean('is_event_boosted')->default(false);
                $table->string('status', 32)->default('draft'); // 7 status SM RatePlanVersion
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('published_at')->nullable();
                $table->timestamps();

                $table->unique(['property_id', 'room_type_id', 'business_date', 'version_code'], 'htl_rate_plan_versions_unique');
            });
        }

        // 5. Tugas Housekeeping Harian & Rute Kerja (D91-6)
        if (!Schema::hasTable('htl_housekeeping_tasks')) {
            Schema::create('htl_housekeeping_tasks', function (Blueprint $table): void {
                $table->id();
                $table->string('property_id', 64);
                $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
                $table->string('room_id', 64);
                $table->foreign('room_id')->references('id')->on('htl_rooms')->cascadeOnDelete();
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('task_date');
                $table->unsignedInteger('credit_points')->default(20);
                $table->boolean('is_priority')->default(false);
                $table->boolean('is_vip')->default(false);
                $table->unsignedInteger('route_sequence')->default(0);
                $table->unsignedInteger('inspection_score')->nullable();
                $table->text('inspection_notes')->nullable();
                $table->string('status', 32)->default('assigned'); // 7 status SM HousekeepingTask
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('inspected_at')->nullable();
                $table->timestamps();

                $table->index(['property_id', 'task_date', 'assigned_user_id', 'status'], 'htl_hk_tasks_lookup_idx');
            });
        }

        // 6. Log Saga Terdistribusi Hotel (D91-2 Overbooking Walk, dsb)
        if (!Schema::hasTable('htl_sagas')) {
            Schema::create('htl_sagas', function (Blueprint $table): void {
                $table->id();
                $table->string('saga_type', 64);
                $table->string('correlation_id', 64)->unique();
                $table->string('current_step', 64);
                $table->string('status', 32)->default('in_progress'); // in_progress, completed, compensating, compensated, failed
                $table->json('payload_json');
                $table->json('step_history_json');
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['saga_type', 'status'], 'htl_sagas_type_status_idx');
            });
        }

        // 7. Sensor IoT Energi Kamar & Log Setback H4 (D91-5)
        if (!Schema::hasTable('htl_room_energy_logs')) {
            Schema::create('htl_room_energy_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('property_id', 64);
                $table->foreign('property_id')->references('id')->on('htl_properties')->cascadeOnDelete();
                $table->string('room_id', 64);
                $table->foreign('room_id')->references('id')->on('htl_rooms')->cascadeOnDelete();
                $table->date('log_date');
                $table->dateTime('event_time');
                $table->boolean('motion_detected')->default(false);
                $table->boolean('setback_active')->default(false);
                $table->unsignedInteger('ac_power_watt')->default(1200);
                $table->unsignedInteger('setback_minutes')->default(0);
                $table->unsignedBigInteger('energy_saved_wh')->default(0);
                $table->boolean('esg_reported')->default(false);
                $table->timestamps();

                $table->index(['property_id', 'room_id', 'log_date'], 'htl_room_energy_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('htl_room_energy_logs');
        Schema::dropIfExists('htl_sagas');
        Schema::dropIfExists('htl_housekeeping_tasks');
        Schema::dropIfExists('htl_rate_plan_versions');
        Schema::dropIfExists('htl_apoa_reports');
        Schema::dropIfExists('htl_digital_keys');
        Schema::dropIfExists('htl_external_factors');
    }
};
