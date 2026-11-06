<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Jaringan Distribusi Air & DMA
        Schema::create('egy_water_networks', function (Blueprint $table) {
            $table->id();
            $table->string('network_name', 100);
            $table->string('zone_code', 50)->unique();
            $table->unsignedBigInteger('capacity_liters_per_day')->default(1000000);
            $table->string('water_source_type', 40)->default('wtp_ipa');
            $table->string('status', 30)->default('operational');
            $table->timestamps();

            $table->index(['zone_code', 'status']);
        });

        // 2. Node Jaringan Air (Pompa, Tandon, Inlet/Outlet DMA, Sensor Tekanan)
        Schema::create('egy_water_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained('egy_water_networks')->cascadeOnDelete();
            $table->string('node_code', 50)->unique();
            $table->string('node_type', 40); // pump_station, reservoir, dma_inlet, dma_outlet, junction
            $table->integer('elevation_m')->default(0);
            $table->unsignedInteger('normal_pressure_kpa')->default(300);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['network_id', 'node_type']);
        });

        // 3. Telemetri Flow & Tekanan Air
        Schema::create('egy_water_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained('egy_water_networks')->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained('egy_water_nodes')->nullOnDelete();
            $table->unsignedBigInteger('meter_id')->nullable();
            $table->unsignedBigInteger('flow_rate_lps')->default(0);
            $table->unsignedBigInteger('cumulative_volume_l')->default(0);
            $table->unsignedInteger('pressure_kpa')->default(0);
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['network_id', 'recorded_at']);
            $table->index(['meter_id', 'recorded_at']);
        });

        // 4. Deteksi Insiden Kebocoran Pipa DMA
        Schema::create('egy_leak_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained('egy_water_networks')->cascadeOnDelete();
            $table->string('zone_code', 50);
            $table->dateTime('detected_at');
            $table->string('severity', 20)->default('MEDIUM'); // LOW, MEDIUM, HIGH, CRITICAL
            $table->unsignedBigInteger('estimated_leak_rate_lps')->default(0);
            $table->unsignedBigInteger('inlet_volume_l')->default(0);
            $table->unsignedBigInteger('billed_volume_l')->default(0);
            $table->unsignedInteger('loss_percentage_bps')->default(0);
            $table->unsignedBigInteger('asset_work_order_id')->nullable();
            $table->string('status', 40)->default('detected'); // detected, dispatched_work_order, repaired, verified_closed
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['network_id', 'status']);
            $table->index(['zone_code', 'detected_at']);
            $table->index('asset_work_order_id');
        });

        // 5. Fasilitas Pengolahan Limbah & Digester Biogas
        Schema::create('egy_waste_treatment_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('facility_name', 100);
            $table->string('facility_code', 50)->unique();
            $table->unsignedInteger('daily_capacity_tons')->default(50);
            $table->unsignedInteger('biogas_yield_m3_per_ton')->default(80);
            $table->unsignedInteger('effluent_bod_mg_l')->default(20);
            $table->unsignedInteger('effluent_cod_mg_l')->default(50);
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['facility_code', 'status']);
        });

        // 6. Penerimaan Tonase Limbah & Tipping Fee
        Schema::create('egy_waste_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('egy_waste_treatment_facilities')->cascadeOnDelete();
            $table->string('vehicle_number', 30);
            $table->string('waste_type', 40); // organic_food, green_waste, sewage_sludge, commercial
            $table->unsignedBigInteger('tonnage_kg');
            $table->unsignedBigInteger('tipping_fee_rate_idr_per_ton');
            $table->unsignedBigInteger('total_tipping_fee_idr');
            $table->unsignedBigInteger('projected_biogas_m3')->default(0);
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->dateTime('recorded_at');
            $table->string('status', 30)->default('settled');
            $table->timestamps();

            $table->index(['facility_id', 'recorded_at']);
            $table->index(['waste_type', 'recorded_at']);
            $table->index('ledger_transaction_id');
        });

        // 7. Produksi Biogas WTE & Listrik
        Schema::create('egy_biogas_wte_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('egy_waste_treatment_facilities')->cascadeOnDelete();
            $table->date('production_date');
            $table->unsignedBigInteger('biogas_volume_m3');
            $table->unsignedBigInteger('electricity_generated_kwh');
            $table->unsignedInteger('methane_content_bps')->default(6500); // 65%
            $table->unsignedInteger('run_hours')->default(24);
            $table->timestamps();

            $table->unique(['facility_id', 'production_date']);
        });

        // 8. Central District Cooling Plants
        Schema::create('egy_district_cooling_plants', function (Blueprint $table) {
            $table->id();
            $table->string('plant_name', 100);
            $table->string('plant_code', 50)->unique();
            $table->unsignedInteger('total_refrigeration_tons')->default(5000);
            $table->unsignedInteger('designed_cop_bps')->default(500); // COP 5.0
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->index(['plant_code', 'status']);
        });

        // 9. Unit Chiller Sentral
        Schema::create('egy_chillers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('egy_district_cooling_plants')->cascadeOnDelete();
            $table->string('chiller_number', 30);
            $table->unsignedInteger('capacity_tons');
            $table->unsignedInteger('rated_power_kw');
            $table->string('refrigerant_type', 30)->default('R134a');
            $table->string('status', 30)->default('running');
            $table->timestamps();

            $table->unique(['plant_id', 'chiller_number']);
        });

        // 10. Telemetri BTU Chilled Water
        Schema::create('egy_chilled_water_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('egy_district_cooling_plants')->cascadeOnDelete();
            $table->foreignId('chiller_id')->nullable()->constrained('egy_chillers')->nullOnDelete();
            $table->integer('supply_temp_c_milli')->default(6500); // 6.5 C
            $table->integer('return_temp_c_milli')->default(12000); // 12.0 C
            $table->integer('delta_temp_c_milli')->default(5500); // 5.5 C
            $table->unsignedBigInteger('flow_rate_lps')->default(0);
            $table->unsignedBigInteger('thermal_kwh_th');
            $table->unsignedBigInteger('electric_kwh');
            $table->unsignedInteger('cop_bps');
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['plant_id', 'recorded_at']);
        });

        // 11. Kontrak ESCO (Energy Service Company)
        Schema::create('egy_esco_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number', 50)->unique();
            $table->string('facility_name', 100);
            $table->unsignedBigInteger('client_party_id');
            $table->unsignedBigInteger('baseline_monthly_kwh');
            $table->unsignedBigInteger('baseline_tariff_idr');
            $table->unsignedInteger('esco_share_bps')->default(7000); // 70%
            $table->unsignedBigInteger('guaranteed_savings_kwh')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('audited'); // audited, proposed, contracted, implementing, measuring, settled, terminated
            $table->timestamps();

            $table->index(['client_party_id', 'status']);
        });

        // 12. Settlement Penghematan ESCO
        Schema::create('egy_esco_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esco_contract_id')->constrained('egy_esco_contracts')->cascadeOnDelete();
            $table->string('period_month', 7); // YYYY-MM
            $table->unsignedBigInteger('baseline_kwh');
            $table->unsignedInteger('cdd_factor_bps')->default(10000);
            $table->unsignedBigInteger('adjusted_baseline_kwh');
            $table->unsignedBigInteger('actual_kwh');
            $table->bigInteger('gross_savings_kwh');
            $table->unsignedBigInteger('rate_idr_per_kwh');
            $table->bigInteger('total_savings_idr');
            $table->unsignedInteger('esco_share_bps');
            $table->bigInteger('esco_share_idr');
            $table->bigInteger('client_share_idr');
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->string('status', 30)->default('calculated'); // calculated, verified, approved, settled, clawbacked
            $table->timestamps();

            $table->unique(['esco_contract_id', 'period_month']);
            $table->index('ledger_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egy_esco_settlements');
        Schema::dropIfExists('egy_esco_contracts');
        Schema::dropIfExists('egy_chilled_water_readings');
        Schema::dropIfExists('egy_chillers');
        Schema::dropIfExists('egy_district_cooling_plants');
        Schema::dropIfExists('egy_biogas_wte_productions');
        Schema::dropIfExists('egy_waste_intakes');
        Schema::dropIfExists('egy_waste_treatment_facilities');
        Schema::dropIfExists('egy_leak_incidents');
        Schema::dropIfExists('egy_water_meter_readings');
        Schema::dropIfExists('egy_water_nodes');
        Schema::dropIfExists('egy_water_networks');
    }
};
