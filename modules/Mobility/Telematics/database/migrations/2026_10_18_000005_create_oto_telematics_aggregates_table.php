<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oto_telematics_aggregates')) {
            Schema::create('oto_telematics_aggregates', function (Blueprint $table) {
                $table->id();
                $table->string('device_id', 64)->index();
                $table->unsignedBigInteger('vehicle_id')->nullable()->index();
                $table->timestamp('window_start');
                $table->timestamp('window_end');
                $table->decimal('avg_speed_kmh', 6, 2)->default(0);
                $table->integer('max_rpm')->default(0);
                $table->decimal('avg_oil_temp_c', 6, 2)->default(90.0);
                $table->decimal('min_battery_voltage', 5, 2)->default(12.6);
                $table->unsignedInteger('tick_count')->default(0);
                $table->timestamps();

                $table->unique(['device_id', 'window_start']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oto_telematics_aggregates');
    }
};
