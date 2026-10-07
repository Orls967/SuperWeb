<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oto_fleet_contracts')) {
            Schema::create('oto_fleet_contracts', function (Blueprint $table) {
                $table->id();
                $table->string('contract_number')->unique();
                $table->unsignedBigInteger('party_id')->index(); // lessee
                $table->unsignedInteger('duration_months')->default(36);
                $table->unsignedInteger('total_units')->default(1);
                $table->unsignedBigInteger('monthly_rental_idr');
                $table->unsignedBigInteger('total_lease_value_idr');
                $table->unsignedBigInteger('accumulated_amortized_idr')->default(0);
                $table->unsignedInteger('max_downtime_hours_per_month')->default(24);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status')->default('active'); // active, completed, terminated
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oto_fleet_contract_units')) {
            Schema::create('oto_fleet_contract_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('oto_fleet_contracts')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('core_vehicles');
                $table->unsignedBigInteger('baseline_odometer_km')->default(0);
                $table->unsignedBigInteger('max_km_per_year')->default(30000);
                $table->unsignedInteger('downtime_hours_recorded')->default(0);
                $table->string('status')->default('assigned'); // assigned, returned, replaced
                $table->timestamps();

                $table->unique(['contract_id', 'vehicle_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oto_fleet_contract_units');
        Schema::dropIfExists('oto_fleet_contracts');
    }
};
