<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oto_ev_stations')) {
            Schema::create('oto_ev_stations', function (Blueprint $table) {
                $table->id();
                $table->string('station_code')->unique();
                $table->string('name');
                $table->string('location_type'); // hub, mall, resto, depot
                $table->string('address');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('transformer_capacity_kw')->default(250);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oto_ev_chargers')) {
            Schema::create('oto_ev_chargers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('station_id')->constrained('oto_ev_stations')->cascadeOnDelete();
                $table->string('charger_code')->unique();
                $table->string('type'); // AC, DC_FAST, DC_ULTRA
                $table->unsignedInteger('max_kw');
                $table->string('status')->default('available'); // available, reserved, charging, offline
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oto_ev_sessions')) {
            Schema::create('oto_ev_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_code')->unique();
                $table->foreignId('charger_id')->constrained('oto_ev_chargers');
                $table->foreignId('vehicle_id')->constrained('core_vehicles');
                $table->foreignId('user_id')->constrained('users');
                $table->timestamp('reserved_from')->nullable();
                $table->timestamp('reserved_to')->nullable();
                $table->timestamp('plugged_in_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('energy_wh')->default(0); // precision in Watt-hours
                $table->unsignedBigInteger('tariff_per_kwh_idr')->default(2500);
                $table->unsignedBigInteger('total_cost_idr')->default(0);
                $table->unsignedBigInteger('deposit_held_idr')->default(0);
                $table->unsignedBigInteger('no_show_fee_idr')->default(0);
                $table->decimal('initial_soh_pct', 5, 2)->default(100.00);
                $table->decimal('final_soh_pct', 5, 2)->default(100.00);
                $table->decimal('peak_cell_temp_c', 5, 2)->default(35.00);
                $table->unsignedInteger('deferred_minutes')->default(0);
                $table->decimal('scope2_co2_kg', 8, 3)->default(0.000);
                $table->decimal('green_km_saved_co2_kg', 8, 3)->default(0.000);
                $table->string('status')->default('reserved'); // reserved, plugged_in, charging, completed, no_show, cancelled
                $table->timestamps();

                $table->index(['charger_id', 'status', 'reserved_from', 'reserved_to'], 'oto_ev_sess_lookup_idx');
            });
        }

        if (! Schema::hasTable('oto_ev_battery_passports')) {
            Schema::create('oto_ev_battery_passports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('core_vehicles')->cascadeOnDelete();
                $table->string('session_code');
                $table->unsignedInteger('cycle_number')->default(1);
                $table->decimal('initial_soh_pct', 5, 2);
                $table->decimal('final_soh_pct', 5, 2);
                $table->decimal('delta_soh', 5, 4)->default(0.0000);
                $table->decimal('max_cell_temp_c', 5, 2)->default(35.00);
                $table->unsignedBigInteger('energy_wh');
                $table->string('prev_hash', 64);
                $table->string('block_hash', 64)->unique();
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->index(['vehicle_id', 'cycle_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oto_ev_battery_passports');
        Schema::dropIfExists('oto_ev_sessions');
        Schema::dropIfExists('oto_ev_chargers');
        Schema::dropIfExists('oto_ev_stations');
    }
};
