<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_supply_chain_shipment_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_code')->unique();
            $table->string('lot_identifier');
            $table->boolean('transit_quality_passed')->default(true); // 381.2 & 381.5 Edge case
            $table->boolean('quarantine_hold_active')->default(false);
            $table->boolean('delivery_to_consignee_permitted')->default(true);
            $table->timestamps();
        });

        Schema::create('global_supply_chain_reverse_manifests', function (Blueprint $table) {
            $table->id();
            $table->string('reverse_manifest_code')->unique();
            $table->string('lot_identifier')->index();
            $table->boolean('has_verified_contract')->default(false); // 381.3, 381.4, 381.6 Risk
            $table->boolean('has_pickup_manifest')->default(false);
            $table->boolean('pickup_authorized')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_supply_chain_reverse_manifests');
        Schema::dropIfExists('global_supply_chain_shipment_tracks');
    }
};
