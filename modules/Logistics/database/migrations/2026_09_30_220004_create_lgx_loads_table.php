<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgx_loads', function (Blueprint $table) {
            $table->id();
            $table->string('load_number', 64)->unique();
            $table->string('load_type', 32); // container, uld, truck
            $table->morphs('loadable'); // loadable_type, loadable_id (Container, Uld, Truck)
            $table->foreignId('schedule_id')->nullable()->constrained('lgx_schedules')->nullOnDelete();
            $table->string('service_type', 32); // fcl, lcl, ftl, ltl, air_uld
            $table->foreignId('origin_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->foreignId('destination_location_id')->constrained('lgx_locations')->cascadeOnDelete();
            $table->string('status', 32)->default('planning'); // planning, consolidating, sealed, loaded, in_transit, unloaded, completed, cancelled

            // Capacity & Utilisation
            $table->decimal('max_weight_kg', 12, 3);
            $table->unsignedInteger('max_volume_dm3');
            $table->decimal('current_weight_kg', 12, 3)->default(0);
            $table->unsignedInteger('current_volume_dm3')->default(0);

            // Cold chain & sealing
            $table->boolean('is_reefer')->default(false);
            $table->integer('target_temp_c10')->nullable();
            $table->string('seal_number', 64)->nullable();
            $table->dateTime('sealed_at')->nullable();

            // SOLAS Verified Gross Mass (VGM)
            $table->decimal('vgm_kg', 12, 3)->nullable();
            $table->string('vgm_method', 16)->nullable(); // method_1, method_2
            $table->string('vgm_certified_by', 100)->nullable();
            $table->dateTime('vgm_verified_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['loadable_type', 'loadable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_loads');
    }
};
