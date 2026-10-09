<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oto_telematics_devices')) {
            Schema::create('oto_telematics_devices', function (Blueprint $table) {
                $table->id();
                $table->string('device_id')->unique();
                $table->foreignId('vehicle_id')->nullable()->constrained('core_vehicles')->nullOnDelete();
                $table->string('serial_number')->unique();
                $table->string('protocol')->default('OBD2_CAN');
                $table->string('status')->default('active'); // active, paired, inactive, grounded
                $table->timestamp('last_heartbeat_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oto_telematics_ticks')) {
            Schema::create('oto_telematics_ticks', function (Blueprint $table) {
                $table->id();
                $table->string('device_id')->index();
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->unsignedBigInteger('sequence');
                $table->timestamp('recorded_at');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->integer('rpm')->default(0);
                $table->decimal('speed_kmh', 6, 2)->default(0);
                $table->decimal('oil_temp_c', 6, 2)->default(90.0);
                $table->decimal('battery_voltage', 5, 2)->default(12.6);
                $table->decimal('fuel_level_pct', 5, 2)->default(100.0);
                $table->string('dtc_code')->nullable(); // e.g., P0117, P0300
                $table->string('idempotency_key')->unique();
                $table->timestamps();

                $table->index(['device_id', 'recorded_at']);
            });
        }

        if (! Schema::hasTable('oto_telematics_baselines')) {
            Schema::create('oto_telematics_baselines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vehicle_id')->unique();
                $table->decimal('avg_oil_temp_c', 6, 2)->default(90.0);
                $table->decimal('avg_battery_voltage', 5, 2)->default(12.6);
                $table->decimal('avg_fuel_consumption_rate', 6, 2)->default(8.5);
                $table->unsignedInteger('sample_count')->default(0);
                $table->timestamp('calculated_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oto_telematics_baselines');
        Schema::dropIfExists('oto_telematics_ticks');
        Schema::dropIfExists('oto_telematics_devices');
    }
};
