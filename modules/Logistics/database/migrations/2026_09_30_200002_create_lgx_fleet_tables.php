<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_trucks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('core_vehicles')->cascadeOnDelete();
            $table->string('plate_number')->unique();
            $table->string('type'); // cde, cdd, fuso, tronton, tractor_head, car_carrier, reefer
            $table->unsignedInteger('payload_kg');
            $table->unsignedInteger('volume_dm3');
            $table->string('required_license'); // SIM B1 Umum / B2 Umum
            $table->unsignedInteger('service_interval_m')->default(10_000_000); // 10,000 km
            $table->unsignedBigInteger('odometer_m')->default(0);
            $table->string('status')->default('available');
            $table->foreignId('current_location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('lgx_trailers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type'); // flatbed_20, flatbed_40, skeletal_20, skeletal_40, reefer
            $table->unsignedInteger('payload_kg');
            $table->string('status')->default('available');
            $table->foreignId('current_location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('lgx_vessels', function (Blueprint $table) {
            $table->id();
            $table->string('imo_number', 7)->unique();
            $table->string('name');
            $table->string('flag', 2)->default('ID');
            $table->unsignedInteger('teu_capacity');
            $table->unsignedInteger('reefer_plugs')->default(0);
            $table->unsignedInteger('dwt_tonnes');
            $table->string('status')->default('available');
            $table->foreignId('current_location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
        });

        Schema::create('lgx_aircraft', function (Blueprint $table) {
            $table->id();
            $table->string('registration')->unique();
            $table->string('type');
            $table->unsignedInteger('max_payload_kg');
            $table->unsignedInteger('uld_positions');
            $table->string('status')->default('available');
            $table->foreignId('current_location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
        });

        Schema::create('lgx_containers', function (Blueprint $table) {
            $table->id();
            $table->string('container_number', 11)->unique(); // ISO 6346
            $table->string('size_type'); // 22G1, 42G1, 45G1, 22R1, 45R1
            $table->unsignedInteger('tare_kg');
            $table->unsignedInteger('max_gross_kg');
            $table->string('status')->default('available');
            $table->foreignId('current_location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['size_type', 'status']);
        });

        Schema::create('lgx_ulds', function (Blueprint $table) {
            $table->id();
            $table->string('uld_code')->unique(); // PMC-xxxx, AKE-xxxx
            $table->string('type'); // PMC, AKE, PAG
            $table->unsignedInteger('tare_kg');
            $table->unsignedInteger('max_gross_kg');
            $table->string('status')->default('available');
            $table->foreignId('current_location_id')->nullable()->constrained('lgx_locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_ulds');
        Schema::dropIfExists('lgx_containers');
        Schema::dropIfExists('lgx_aircraft');
        Schema::dropIfExists('lgx_vessels');
        Schema::dropIfExists('lgx_trailers');
        Schema::dropIfExists('lgx_trucks');
    }
};
