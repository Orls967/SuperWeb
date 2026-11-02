<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oto_telematics_devices')) {
            Schema::table('oto_telematics_devices', function (Blueprint $table) {
                if (! Schema::hasColumn('oto_telematics_devices', 'token_hash')) {
                    $table->string('token_hash', 64)->nullable()->after('serial_number');
                }
            });
        }

        if (Schema::hasTable('oto_telematics_ticks')) {
            Schema::table('oto_telematics_ticks', function (Blueprint $table) {
                if (! Schema::hasColumn('oto_telematics_ticks', 'coolant_temp_deci_c')) {
                    $table->integer('coolant_temp_deci_c')->nullable();
                }
                if (! Schema::hasColumn('oto_telematics_ticks', 'battery_mv')) {
                    $table->integer('battery_mv')->nullable();
                }
                if (! Schema::hasColumn('oto_telematics_ticks', 'fuel_permil')) {
                    $table->integer('fuel_permil')->nullable();
                }
                if (! Schema::hasColumn('oto_telematics_ticks', 'odometer_meters')) {
                    $table->unsignedBigInteger('odometer_meters')->nullable();
                }
                if (! Schema::hasColumn('oto_telematics_ticks', 'dtc_codes_json')) {
                    $table->json('dtc_codes_json')->nullable();
                }
                if (! Schema::hasColumn('oto_telematics_ticks', 'latitude_e6')) {
                    $table->integer('latitude_e6')->nullable();
                }
                if (! Schema::hasColumn('oto_telematics_ticks', 'longitude_e6')) {
                    $table->integer('longitude_e6')->nullable();
                }
            });
        }

        if (Schema::hasTable('oto_telematics_baselines')) {
            Schema::table('oto_telematics_baselines', function (Blueprint $table) {
                if (! Schema::hasColumn('oto_telematics_baselines', 'metric')) {
                    $table->string('metric')->default('coolant_temp_deci_c');
                }
                if (! Schema::hasColumn('oto_telematics_baselines', 'median_value')) {
                    $table->bigInteger('median_value')->default(0);
                }
                if (! Schema::hasColumn('oto_telematics_baselines', 'mad_value')) {
                    $table->bigInteger('mad_value')->default(0);
                }
                if (! Schema::hasColumn('oto_telematics_baselines', 'window_days')) {
                    $table->unsignedInteger('window_days')->default(7);
                }
            });
        }

        if (! Schema::hasTable('oto_telematics_sequence_gaps')) {
            Schema::create('oto_telematics_sequence_gaps', function (Blueprint $table) {
                $table->id();
                $table->string('device_id')->index();
                $table->foreign('device_id')->references('device_id')->on('oto_telematics_devices')->cascadeOnDelete();
                $table->unsignedBigInteger('expected_sequence');
                $table->unsignedBigInteger('received_sequence');
                $table->timestamp('detected_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oto_telematics_anomalies')) {
            Schema::create('oto_telematics_anomalies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vehicle_id')->index();
                $table->string('metric');
                $table->date('anomaly_date');
                $table->bigInteger('observed_value');
                $table->bigInteger('threshold_value');
                $table->foreignId('draft_booking_id')->nullable()->constrained('serve_bookings')->nullOnDelete();
                $table->timestamps();

                $table->unique(['vehicle_id', 'metric', 'anomaly_date'], 'oto_anomalies_veh_metric_date_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oto_telematics_anomalies');
        Schema::dropIfExists('oto_telematics_sequence_gaps');
    }
};
