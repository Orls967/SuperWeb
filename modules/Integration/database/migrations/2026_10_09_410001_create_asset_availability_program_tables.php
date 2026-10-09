<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ops_asset_availability_pools', function (Blueprint $table) {
            $table->id();
            $table->string('asset_class')->unique(); // truck, crane, bed, room, machine, charger
            $table->integer('total_units');
            $table->integer('maintenance_reserve_units'); // 410.1 & 410.4
            $table->integer('buffer_capacity_units'); // 410.2
            $table->integer('allocated_units')->default(0);
            $table->boolean('critical_service_line')->default(false); // health, safety-critical logistics
            $table->timestamps();
        });

        Schema::create('ops_asset_shortage_escalations', function (Blueprint $table) {
            $table->id();
            $table->string('escalation_code')->unique();
            $table->string('asset_class');
            $table->string('resolution_type'); // substitute, defer_consent, third_party_rental
            $table->string('substitute_asset_class')->nullable();
            $table->boolean('quality_gate_approved')->default(true); // 410.6
            $table->decimal('additional_cost', 18, 2)->default(0.00);
            $table->string('approved_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_asset_shortage_escalations');
        Schema::dropIfExists('ops_asset_availability_pools');
    }
};
