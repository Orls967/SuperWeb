<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('truck_id')->constrained('lgx_trucks')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('lgx_drivers')->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('lgx_schedules')->nullOnDelete();
            $table->unsignedInteger('liters_x1000'); // liter x 1000 (integer)
            $table->unsignedInteger('price_per_liter_idr');
            $table->unsignedBigInteger('total_cost_idr');
            $table->unsignedBigInteger('odometer_m'); // meter, isi penuh (full tank)
            $table->unsignedBigInteger('previous_odometer_m')->nullable();
            $table->unsignedBigInteger('distance_m')->nullable();
            $table->unsignedInteger('km_per_liter_x100')->nullable(); // km/l x 100
            $table->unsignedInteger('baseline_km_per_liter_x100')->nullable();
            $table->integer('deviation_bp')->nullable(); // selisih terhadap baseline dalam basis poin
            $table->boolean('is_anomaly')->default(false);
            $table->string('anomaly_note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('filled_at');
            $table->timestamps();

            $table->index(['truck_id', 'filled_at']);
            $table->index('is_anomaly');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_fuel_logs');
    }
};
