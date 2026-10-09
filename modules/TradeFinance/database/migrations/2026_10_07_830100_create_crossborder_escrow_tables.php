<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 83.1 Cross-border crypto stablecoin escrow contracts
        Schema::create('tf_crossborder_escrows', function (Blueprint $table) {
            $table->id();
            $table->string('escrow_code', 32)->unique();
            $table->unsignedBigInteger('importer_id');
            $table->unsignedBigInteger('exporter_id');
            $table->string('stablecoin_asset', 16)->default('USDC'); // Simulated stablecoin
            $table->bigInteger('amount_cents'); // Minor unit integer e.g. 100000 cents = $1,000 USD
            $table->bigInteger('locked_fx_rate_idr'); // Fixed locked exchange rate integer (e.g. 16000 IDR/USD)
            $table->string('bl_number', 64)->unique(); // Bill of lading number
            $table->string('bl_document_hash', 64);
            $table->string('pod_chain_hash', 64)->nullable();
            $table->string('status', 32)->default('DEPOSITED'); // DEPOSITED, RELEASED, DISPUTED, REFUNDED
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        // 83.3 & 83.4 EU CBAM (Carbon Border Adjustment Mechanism) compliance certifications
        Schema::create('tf_cbam_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('cert_code', 32)->unique();
            $table->string('container_number', 32)->unique();
            $table->string('origin_factory_code', 32);
            $table->string('commodity_code', 32); // STEEL, ALUMINUM, CEMENT, FERTILIZER
            $table->decimal('net_mass_tons', 8, 2);
            $table->decimal('embedded_emissions_tco2', 8, 2); // Ton CO2e embedded
            $table->decimal('cbam_benchmark_factor', 6, 3)->default(1.25);
            $table->bigInteger('cbam_levy_cost_idr'); // Simulated CBAM levy
            $table->string('certificate_hash', 64);
            $table->string('status', 32)->default('CERTIFIED');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tf_cbam_certificates');
        Schema::dropIfExists('tf_crossborder_escrows');
    }
};
