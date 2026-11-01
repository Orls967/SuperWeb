<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oto_ev_stations')) {
            Schema::table('oto_ev_stations', function (Blueprint $table) {
                if (! Schema::hasColumn('oto_ev_stations', 'timezone')) {
                    $table->string('timezone')->default('Asia/Jakarta');
                }
                if (! Schema::hasColumn('oto_ev_stations', 'max_grid_power_kw')) {
                    $table->unsignedInteger('max_grid_power_kw')->default(100);
                }
            });
        }

        if (Schema::hasTable('oto_ev_sessions')) {
            Schema::table('oto_ev_sessions', function (Blueprint $table) {
                if (! Schema::hasColumn('oto_ev_sessions', 'started_at')) {
                    $table->timestamp('started_at')->nullable();
                }
                if (! Schema::hasColumn('oto_ev_sessions', 'refund_idr')) {
                    $table->unsignedBigInteger('refund_idr')->default(0);
                }
                if (! Schema::hasColumn('oto_ev_sessions', 'final_soh_bps')) {
                    $table->unsignedInteger('final_soh_bps')->default(10000);
                }
            });
        }

        if (! Schema::hasTable('oto_ev_tariff_windows')) {
            Schema::create('oto_ev_tariff_windows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('station_id')->constrained('oto_ev_stations')->cascadeOnDelete();
                $table->string('name');
                $table->string('start_time');
                $table->string('end_time');
                $table->unsignedBigInteger('rate_per_kwh_idr');
                $table->boolean('is_cross_midnight')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oto_ev_meter_values')) {
            Schema::create('oto_ev_meter_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('session_id')->constrained('oto_ev_sessions')->cascadeOnDelete();
                $table->timestamp('read_at');
                $table->unsignedBigInteger('energy_wh');
                $table->unsignedInteger('power_w')->default(0);
                $table->string('idempotency_key')->unique();
                $table->timestamps();

                $table->index(['session_id', 'read_at']);
            });
        }

        if (! Schema::hasTable('oto_ev_smart_charging_plans')) {
            Schema::create('oto_ev_smart_charging_plans', function (Blueprint $table) {
                $table->id();
                $table->string('plan_uuid')->unique();
                $table->foreignId('station_id')->constrained('oto_ev_stations')->cascadeOnDelete();
                $table->string('time_slot');
                $table->unsignedInteger('allocated_power_kw')->default(0);
                $table->json('certificate_json');
                $table->string('status')->default('VALID');
                $table->timestamps();

                $table->index(['station_id', 'time_slot']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oto_ev_smart_charging_plans');
        Schema::dropIfExists('oto_ev_meter_values');
        Schema::dropIfExists('oto_ev_tariff_windows');
    }
};
