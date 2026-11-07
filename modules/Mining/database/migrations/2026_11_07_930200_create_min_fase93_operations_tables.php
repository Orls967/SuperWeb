<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('min_mine_plans')) {
            Schema::create('min_mine_plans', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('plan_code')->unique();
                $table->string('period_month', 7); // YYYY-MM
                $table->string('commodity', 32);
                $table->unsignedBigInteger('target_cut_kg')->default(0);
                $table->unsignedBigInteger('target_fill_kg')->default(0);
                $table->unsignedBigInteger('target_ore_kg')->default(0);
                $table->unsignedBigInteger('target_grade_ppm')->default(0);
                $table->string('status', 32)->default('draft');
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_mine_plan_shifts')) {
            Schema::create('min_mine_plan_shifts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('mine_plan_id')->constrained('min_mine_plans')->cascadeOnDelete();
                $table->foreignUuid('pit_id')->constrained('min_pits')->cascadeOnDelete();
                $table->date('shift_date');
                $table->string('shift_code', 32); // SHIFT_A, SHIFT_B
                $table->unsignedBigInteger('target_tonnage_kg')->default(0);
                $table->unsignedBigInteger('actual_tonnage_kg')->default(0);
                $table->unsignedInteger('effective_hours_nominal')->default(10);
                $table->unsignedInteger('effective_hours_adjusted')->default(10);
                $table->unsignedInteger('rain_downtime_hours')->default(0);
                $table->unsignedInteger('maintenance_downtime_hours')->default(0);
                $table->bigInteger('variance_kg')->default(0);
                $table->timestamps();

                $table->unique(['mine_plan_id', 'pit_id', 'shift_date', 'shift_code'], 'min_plan_shift_unique');
            });
        }

        if (! Schema::hasTable('min_haul_cycles')) {
            Schema::create('min_haul_cycles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->foreignUuid('pit_id')->constrained('min_pits')->cascadeOnDelete();
                $table->foreignUuid('truck_id')->constrained('min_equipment')->cascadeOnDelete();
                $table->foreignUuid('shovel_id')->nullable()->constrained('min_equipment')->nullOnDelete();
                $table->date('shift_date');
                $table->string('shift_code', 32);
                $table->unsignedBigInteger('cycle_seq');
                $table->string('status', 32)->default('queued');
                $table->unsignedBigInteger('target_payload_kg')->default(0);
                $table->unsignedBigInteger('actual_payload_kg')->default(0);
                $table->unsignedInteger('distance_meters')->default(0);
                $table->unsignedBigInteger('fuel_consumed_ml')->default(0);
                $table->unsignedBigInteger('ton_km_scaled')->default(0);
                $table->boolean('is_override')->default(false);
                $table->text('override_reason')->nullable();
                $table->foreignId('override_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['truck_id', 'shift_date', 'shift_code', 'cycle_seq'], 'min_haul_cycle_truck_seq_unique');
            });
        }

        if (! Schema::hasTable('min_haul_cycle_histories')) {
            Schema::create('min_haul_cycle_histories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('haul_cycle_id')->constrained('min_haul_cycles')->cascadeOnDelete();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->string('actor_ref')->default('system');
                $table->text('notes')->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_equipment_telemetries')) {
            Schema::create('min_equipment_telemetries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('equipment_id')->constrained('min_equipment')->cascadeOnDelete();
                $table->unsignedBigInteger('sequence_number');
                $table->timestamp('recorded_at')->nullable();
                $table->unsignedBigInteger('engine_hours_total')->default(0);
                $table->unsignedInteger('fuel_level_percent')->default(100);
                $table->unsignedInteger('instant_fuel_rate_ml_s')->default(0);
                $table->unsignedBigInteger('payload_kg')->default(0);
                $table->unsignedInteger('vibration_rms_scaled')->default(0);
                $table->integer('latitude_scaled')->default(0);
                $table->integer('longitude_scaled')->default(0);
                $table->timestamps();

                $table->unique(['equipment_id', 'sequence_number'], 'min_equip_telemetry_seq_unique');
            });
        }

        if (! Schema::hasTable('min_fuel_anomaly_cases')) {
            Schema::create('min_fuel_anomaly_cases', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('case_number')->unique();
                $table->foreignUuid('site_id')->constrained('min_sites')->cascadeOnDelete();
                $table->string('contractor_party_ref');
                $table->foreignUuid('equipment_id')->constrained('min_equipment')->cascadeOnDelete();
                $table->date('shift_date');
                $table->string('shift_code', 32);
                $table->unsignedBigInteger('actual_fuel_ml')->default(0);
                $table->unsignedBigInteger('baseline_fuel_ml')->default(0);
                $table->unsignedBigInteger('excess_ml')->default(0);
                $table->integer('excess_bps')->default(0);
                $table->unsignedBigInteger('held_amount_idr')->default(0);
                $table->string('status', 32)->default('detected');
                $table->text('dispute_reason')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_fuel_anomaly_histories')) {
            Schema::create('min_fuel_anomaly_histories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('case_id')->constrained('min_fuel_anomaly_cases')->cascadeOnDelete();
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->string('actor_ref')->default('system');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_contractor_payment_holds')) {
            Schema::create('min_contractor_payment_holds', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('hold_reference')->unique();
                $table->string('contractor_party_ref');
                $table->foreignUuid('case_id')->constrained('min_fuel_anomaly_cases')->cascadeOnDelete();
                $table->unsignedBigInteger('hold_amount_idr')->default(0);
                $table->unsignedBigInteger('released_amount_idr')->default(0);
                $table->unsignedBigInteger('deducted_amount_idr')->default(0);
                $table->string('status', 32)->default('HELD');
                $table->string('idempotency_key')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('min_external_factors')) {
            Schema::create('min_external_factors', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('factor_key', 64);
                $table->unsignedInteger('version')->default(1);
                $table->date('effective_from');
                $table->string('source');
                $table->string('label', 32); // regulasi, asumsi_simulasi, fixture
                $table->unsignedBigInteger('numeric_value_scaled')->default(0);
                $table->string('unit', 32);
                $table->text('metadata_json')->nullable();
                $table->timestamps();

                $table->unique(['factor_key', 'version'], 'min_external_factors_key_version_unique');
            });
        }

        if (! Schema::hasTable('min_mining_sagas')) {
            Schema::create('min_mining_sagas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('saga_type', 64);
                $table->string('saga_reference')->unique();
                $table->string('current_step', 64);
                $table->string('status', 32)->default('STARTED');
                $table->text('payload_json');
                $table->text('compensation_log_json')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('min_mining_sagas');
        Schema::dropIfExists('min_external_factors');
        Schema::dropIfExists('min_contractor_payment_holds');
        Schema::dropIfExists('min_fuel_anomaly_histories');
        Schema::dropIfExists('min_fuel_anomaly_cases');
        Schema::dropIfExists('min_equipment_telemetries');
        Schema::dropIfExists('min_haul_cycle_histories');
        Schema::dropIfExists('min_haul_cycles');
        Schema::dropIfExists('min_mine_plan_shifts');
        Schema::dropIfExists('min_mine_plans');
    }
};
