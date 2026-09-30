<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // seaport, airport, hub, depot, warehouse, cfs, customer_point
            $table->string('unlocode', 5)->nullable()->index();
            $table->string('iata', 3)->nullable()->index();
            $table->string('city');
            $table->string('province');
            $table->string('country', 2)->default('ID'); // ISO-3166 alpha-2
            $table->integer('lat_e6'); // Microdegrees: degrees * 1,000,000
            $table->integer('lng_e6'); // Microdegrees: degrees * 1,000,000
            $table->string('timezone')->default('Asia/Makassar');
            $table->unsignedInteger('min_connection_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['city', 'province']);
        });

        Schema::create('lgx_lanes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->string('mode'); // road, sea, air
            $table->unsignedBigInteger('distance_m'); // distance in meters
            $table->unsignedInteger('standard_transit_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['origin_id', 'destination_id', 'mode'], 'lgx_lanes_orig_dest_mode_unique');
            $table->index(['mode', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_lanes');
        Schema::dropIfExists('lgx_locations');
    }
};
