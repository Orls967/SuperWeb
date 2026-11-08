<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Pembenihan & Indukan Patogen Bebas (Hatchery & SPF Broodstock)
        Schema::create('mar_hatcheries', function (Blueprint $table): void {
            $table->id();
            $table->string('hatchery_code', 32)->unique();
            $table->string('name', 120);
            $table->string('operator_name', 120);
            $table->string('province', 60);
            $table->string('spf_certificate_number', 64)->unique();
            $table->string('broodstock_origin', 100); // e.g. Kona Bay Hawaii, SIS Florida, Benchmark
            $table->string('biosecurity_level', 20)->default('LEVEL_3'); // LEVEL_1, LEVEL_2, LEVEL_3
            $table->date('certified_at');
            $table->date('expires_at');
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, SUSPENDED, REVOKED
            $table->timestamps();
        });

        // 2. Batch Siklus Budidaya Akuakultur (Aquaculture Batch Lifecycle)
        Schema::create('mar_aquaculture_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_code', 40)->unique();
            $table->foreignId('pond_id')->constrained('mar_ponds')->cascadeOnDelete();
            $table->foreignId('hatchery_id')->constrained('mar_hatcheries')->cascadeOnDelete();
            $table->string('species_scientific_name', 80); // Litopenaeus vannamei, Epinephelus fuscoguttatus, Scylla serrata, Eucheuma cottonii
            $table->string('commercial_name', 60); // Whiteleg Shrimp, Tiger Grouper, Mud Crab, Seaweed
            $table->unsignedInteger('initial_stock_count');
            $table->decimal('initial_density_per_m2', 8, 2);
            $table->decimal('initial_average_body_weight_grams', 8, 3);
            $table->date('stocking_date');
            $table->date('expected_harvest_date');
            $table->decimal('target_harvest_weight_grams', 8, 2);
            $table->string('certification_scope', 60)->default('ASC_BAP'); // ASC, BAP_4_STAR, GLOBAL_GAP, MSC
            $table->string('stage', 30)->default('HATCHERY'); // HATCHERY, NURSERY, GROW_OUT, PRE_HARVEST, HARVESTED, CANCELLED
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });

        // 3. Telemetri Kualitas Air Tambak IoT (Water Quality Telemetry)
        Schema::create('mar_water_quality_telemetries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('mar_aquaculture_batches')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('dissolved_oxygen_mg_l', 6, 2); // Target: 4.5 - 7.5 mg/L
            $table->decimal('ph_level', 5, 2); // Target: 7.5 - 8.5
            $table->decimal('water_temperature_celsius', 5, 2); // Target: 28.0 - 31.5 C
            $table->decimal('salinity_ppt', 5, 2); // Target: 15.0 - 28.0 ppt
            $table->decimal('total_ammonia_nitrogen_ppm', 6, 3); // Max: 0.1 mg/L NH3
            $table->decimal('nitrite_no2_ppm', 6, 3);
            $table->decimal('alkalinity_mg_l', 6, 2); // Min: 120 mg/L
            $table->decimal('oxidation_reduction_potential_mv', 6, 1);
            $table->string('sensor_device_uuid', 64);
            $table->boolean('aerator_active')->default(true);
            $table->string('water_quality_grade', 20)->default('OPTIMAL'); // OPTIMAL, ACCEPTABLE, CRITICAL_ANOXIA, STRESS
            $table->timestamps();
        });

        // 4. Batch Inventori Pakan Ramah Lingkungan (Certified Sustainable Feed Batches)
        Schema::create('mar_feed_inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('feed_lot_number', 40)->unique();
            $table->string('manufacturer_name', 120);
            $table->string('feed_product_name', 80);
            $table->decimal('protein_percentage', 5, 2); // e.g. 35.0% - 40.0%
            $table->decimal('lipid_percentage', 5, 2);
            $table->boolean('marine_raw_materials_sustainable')->default(true); // IFFO RS / MarinTrust certified fishmeal
            $table->string('feed_certification', 40)->default('ASC_FEED_STANDARD');
            $table->unsignedInteger('quantity_kg');
            $table->unsignedInteger('remaining_kg');
            $table->unsignedBigInteger('unit_price_minor_cents');
            $table->date('manufactured_at');
            $table->date('expires_at');
            $table->timestamps();
        });

        // 5. Pencatatan Pemberian Pakan Harian & Perhitungan FCR (Feeding Events)
        Schema::create('mar_feeding_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('mar_aquaculture_batches')->cascadeOnDelete();
            $table->foreignId('feed_inventory_batch_id')->constrained('mar_feed_inventory_batches')->cascadeOnDelete();
            $table->date('feeding_date');
            $table->unsignedSmallInteger('feeding_tray_number')->default(1);
            $table->decimal('quantity_dispensed_kg', 8, 2);
            $table->decimal('estimated_feed_consumption_pct', 5, 2)->default(95.0);
            $table->decimal('daily_sampling_abw_grams', 8, 2);
            $table->decimal('daily_fcr_estimate', 6, 3);
            $table->string('feeding_technique', 30)->default('AUTO_FEEDER_ACOUSTIC'); // MANUAL_BROADCAST, AUTO_FEEDER_TIMER, AUTO_FEEDER_ACOUSTIC
            $table->timestamps();
        });

        // 6. Pemeriksaan Patogen & Karantina Kesehatan Ikan/Udang (Disease Health Screening)
        Schema::create('mar_disease_health_screenings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('mar_aquaculture_batches')->cascadeOnDelete();
            $table->date('screening_date');
            $table->string('screening_agency', 100);
            $table->string('sample_barcode', 64)->unique();
            $table->boolean('wssv_pcr_negative')->default(true); // White Spot Syndrome Virus
            $table->boolean('imnv_pcr_negative')->default(true); // Infectious Myonecrosis Virus
            $table->boolean('ehp_pcr_negative')->default(true); // Enterocytozoon hepatopenaei
            $table->boolean('ahpnd_pcr_negative')->default(true); // Acute Hepatopancreatic Necrosis Disease (EMS)
            $table->boolean('vibrio_parahaemolyticus_controlled')->default(true);
            $table->string('hepatopancreas_condition', 30)->default('HEALTHY_LIPIDS_FULL');
            $table->string('quarantine_status', 20)->default('CLEARED'); // CLEARED, QUARANTINED, DEPOPULATION_ORDERED
            $table->timestamps();
        });

        // 7. Operasi Pemanenan Akuakultur Ekspor (Aquaculture Harvest Operations)
        Schema::create('mar_harvest_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('harvest_code', 40)->unique();
            $table->foreignId('batch_id')->constrained('mar_aquaculture_batches')->cascadeOnDelete();
            $table->timestamp('harvested_at');
            $table->string('harvest_method', 30)->default('DRAIN_HARVEST_LIVE_PUMP'); // MECHANICAL_PUMP, CAST_NET_PARTIAL, DRAIN_HARVEST_LIVE_PUMP
            $table->decimal('slurry_ice_temperature_celsius', 5, 2)->default(-1.5); // Rapid chill killing at -1.5 C to preserve muscle rigor
            $table->decimal('gross_biomass_harvested_kg', 10, 2);
            $table->decimal('survival_rate_percentage', 5, 2);
            $table->decimal('final_fcr', 6, 3);
            $table->unsignedInteger('average_count_per_kg'); // Shrimp count size: 30s, 40s, 50s
            $table->string('chain_of_custody_cert', 60);
            $table->string('lead_technician_name', 100);
            $table->string('status', 20)->default('COMPLETED');
            $table->timestamps();
        });

        // 8. Lot Hasil Panen & Klasifikasi Mutu Ekspor (Harvest Export Lots)
        Schema::create('mar_export_lots', function (Blueprint $table): void {
            $table->id();
            $table->string('lot_tracking_number', 50)->unique();
            $table->foreignId('harvest_operation_id')->constrained('mar_harvest_operations')->cascadeOnDelete();
            $table->string('product_form', 40); // HEAD_ON_SHELL_ON, HEADLESS_SHELL_ON, PEELED_DEVEINED, LIVE_GROUPER
            $table->string('commercial_grade', 20); // GRADE_A1, GRADE_A, GRADE_B, REJECT
            $table->string('size_classification', 30); // 16_20, 21_25, 26_30, 31_40, 41_50
            $table->decimal('net_lot_weight_kg', 10, 2);
            $table->unsignedInteger('pack_count_cartons');
            $table->decimal('defects_organoleptic_pct', 4, 2)->default(0.5); // Soft shell, melanosis, broken appendages
            $table->unsignedBigInteger('estimated_export_value_minor_cents');
            $table->string('freeze_method', 30)->default('INDIVIDUALLY_QUICK_FROZEN'); // IQF, SEMI_IQF, CONTACT_PLATE_BLOCK
            $table->string('storage_room_code', 30)->default('FREEZER_ROOM_B3');
            $table->timestamps();
        });

        // 9. Pemrosesan Pabrik & Pengemasan Rantai Dingin (Processing & Packaging Facilities)
        Schema::create('mar_processing_packagings', function (Blueprint $table): void {
            $table->id();
            $table->string('packaging_slip_number', 50)->unique();
            $table->foreignId('export_lot_id')->constrained('mar_export_lots')->cascadeOnDelete();
            $table->string('processing_plant_code', 32); // EU Approval Code e.g. ID-PLANT-042
            $table->string('haccp_plan_reference', 40);
            $table->decimal('core_temperature_celsius', 5, 2)->default(-22.0); // Wajib <= -18.0 C
            $table->decimal('glazing_water_percentage', 5, 2)->default(8.0); // Protective ice glaze 5% - 10%
            $table->string('barcode_gtin14', 32);
            $table->string('packaging_material', 40)->default('FOOD_GRADE_CORRUGATED_WAXED');
            $table->date('packed_at');
            $table->date('best_before_date');
            $table->string('sanitation_inspector_name', 100);
            $table->timestamps();
        });

        // 10. Pengujian Laboratorium Residu & Kontaminan (Laboratory Assays & Residue Screening)
        Schema::create('mar_laboratory_assays', function (Blueprint $table): void {
            $table->id();
            $table->string('assay_certificate_number', 50)->unique();
            $table->foreignId('export_lot_id')->constrained('mar_export_lots')->cascadeOnDelete();
            $table->string('accredited_laboratory_name', 120); // ISO/IEC 17025 accredited lab
            $table->date('tested_at');
            $table->decimal('chloramphenicol_ppb', 6, 3)->default(0.000); // Standard: ND (< 0.3 ppb MRPL EU)
            $table->decimal('nitrofuran_aoz_ppb', 6, 3)->default(0.000); // Standard: ND (< 0.5 ppb)
            $table->decimal('nitrofuran_amoz_ppb', 6, 3)->default(0.000);
            $table->decimal('fluoroquinolones_ppb', 6, 3)->default(0.000); // Enrofloxacin/Ciprofloxacin
            $table->decimal('malachite_green_ppb', 6, 3)->default(0.000);
            $table->decimal('histamine_ppm', 6, 2)->default(2.50); // Standard: < 50 ppm FDA / < 100 ppm EU
            $table->decimal('heavy_metal_cadmium_ppm', 6, 3)->default(0.020); // Standard: < 0.5 ppm
            $table->decimal('heavy_metal_lead_ppm', 6, 3)->default(0.015);
            $table->decimal('microbiology_salmonella_cfu_25g', 4, 1)->default(0.0); // Absent in 25g
            $table->string('overall_compliance_verdict', 20)->default('PASSED_FOR_EXPORT'); // PASSED_FOR_EXPORT, FAILED_DESTROY_ORDER
            $table->string('signed_analyst_name', 100);
            $table->timestamps();
        });

        // 11. Simpul Silsilah Kriptografis Merkle Proof Ketertelusuran Lot (Traceability Lot Nodes)
        Schema::create('mar_traceability_lot_nodes', function (Blueprint $table): void {
            $table->id();
            $table->string('node_uuid', 64)->unique();
            $table->foreignId('export_lot_id')->constrained('mar_export_lots')->cascadeOnDelete();
            $table->unsignedInteger('leaf_index');
            $table->string('hatchery_data_hash', 64);
            $table->string('feed_cycle_hash', 64);
            $table->string('water_quality_hash', 64);
            $table->string('harvest_event_hash', 64);
            $table->string('assay_lab_hash', 64);
            $table->string('combined_leaf_hash', 64);
            $table->string('merkle_root_hash', 64)->index();
            $table->json('merkle_proof_path'); // Array of sibling hashes for lightweight public verification
            $table->timestamp('anchored_at');
            $table->timestamps();
        });

        // 12. Pesanan Ekspor Internasional & Kontrak Serap (International Export Orders)
        Schema::create('mar_export_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('buyer_party_id')->constrained('users')->cascadeOnDelete();
            $table->string('destination_country_code', 4); // US, JP, EU, CN, CA, AU
            $table->string('destination_port_unlocode', 10); // USLAX, JPTYO, NLRTM, CNSHA
            $table->string('incoterm', 10)->default('CIF'); // FOB, CIF, CFR, DDP
            $table->decimal('total_ordered_weight_kg', 10, 2);
            $table->unsignedBigInteger('total_contract_value_minor_cents');
            $table->string('payment_instrument', 30)->default('LETTER_OF_CREDIT'); // LETTER_OF_CREDIT, ESCROW_DEPOSIT, CAD
            $table->string('letter_of_credit_number', 50)->nullable();
            $table->string('issuing_bank_swift', 20)->nullable();
            $table->string('status', 20)->default('CONFIRMED'); // DRAFT, CONFIRMED, PACKED, IN_TRANSIT, SETTLED, CANCELLED
            $table->timestamps();
        });

        // 13. Pengiriman Kontainer Reefer Ekspor (Export Reefer Container Shipments)
        Schema::create('mar_export_shipments', function (Blueprint $table): void {
            $table->id();
            $table->string('shipment_tracking_number', 50)->unique();
            $table->foreignId('export_order_id')->constrained('mar_export_orders')->cascadeOnDelete();
            $table->foreignId('export_lot_id')->constrained('mar_export_lots')->cascadeOnDelete();
            $table->string('container_iso_number', 20); // e.g. MSKU1234567 (40ft High Cube Reefer)
            $table->string('shipping_line_carrier', 60); // Maersk, MSC, ONE, CMA CGM
            $table->string('booking_reference', 40);
            $table->string('ocean_bill_of_lading', 40)->unique();
            $table->string('health_certificate_official_number', 50)->unique(); // Karantina Ikan KKP
            $table->decimal('temperature_setpoint_celsius', 5, 2)->default(-22.0);
            $table->timestamp('stuffed_and_sealed_at');
            $table->timestamp('estimated_departure_at');
            $table->timestamp('estimated_arrival_at');
            $table->string('shipment_status', 30)->default('BOOKED'); // BOOKED, STAGED_COLD_STORAGE, HEALTH_CERT_ISSUED, LOADED_ON_VESSEL, IN_TRANSIT, CUSTOMS_CLEARED, DELIVERED
            $table->timestamps();
        });

        // 14. Telemetri Rantai Dingin Kontainer Reefer (Container Cold-Chain Telemetries)
        Schema::create('mar_cold_chain_telemetry_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('export_shipment_id')->constrained('mar_export_shipments')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('ambient_temperature_celsius', 5, 2);
            $table->decimal('supply_air_temperature_celsius', 5, 2);
            $table->decimal('return_air_temperature_celsius', 5, 2);
            $table->decimal('cargo_probe_temperature_celsius', 5, 2);
            $table->decimal('humidity_percentage', 5, 2);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('compressor_running')->default(true);
            $table->boolean('defrost_cycle_active')->default(false);
            $table->boolean('temperature_breach_detected')->default(false);
            $table->timestamps();
        });

        // 15. Penyelesaian Escrow Hasil Panen & Bagi Hasil Petambak (Escrow Harvest Settlements)
        Schema::create('mar_escrow_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('settlement_code', 50)->unique();
            $table->foreignId('export_order_id')->constrained('mar_export_orders')->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('gross_harvest_value_minor_cents');
            $table->unsignedBigInteger('feed_deduction_minor_cents');
            $table->unsignedBigInteger('hatchery_seed_deduction_minor_cents');
            $table->unsignedBigInteger('quality_grade_premium_minor_cents');
            $table->unsignedBigInteger('net_farmer_payout_minor_cents');
            $table->string('escrow_hold_reference', 64)->unique();
            $table->timestamp('released_at')->nullable();
            $table->string('status', 20)->default('HELD'); // HELD, RELEASED, REFUNDED, DISPUTED
            $table->timestamps();
        });

        // 16. Proyek Konservasi & Restorasi Mangrove Pesisir (Mangrove Restoration Projects)
        Schema::create('mar_mangrove_restoration_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('project_code', 40)->unique();
            $table->string('project_name', 120);
            $table->string('coastal_regency', 80); // e.g. Berau, Tarakan, Kotabaru, Banyuwangi
            $table->decimal('restoration_area_hectares', 8, 2);
            $table->string('dominant_mangrove_species', 80); // Rhizophora mucronata, Avicennia marina, Bruguiera gymnorhiza
            $table->unsignedInteger('trees_planted_count');
            $table->decimal('survival_rate_percentage', 5, 2)->default(88.5);
            $table->date('initiated_at');
            $table->string('verra_registry_code', 50)->nullable();
            $table->string('status', 20)->default('MONITORED'); // PLANTING, MONITORED, VERIFIED_CREDIT
            $table->timestamps();
        });

        // 17. Penilaian Stok Karbon Biru Berbasis Verra VM0033 (Blue Carbon Assessments)
        Schema::create('mar_blue_carbon_assessments', function (Blueprint $table): void {
            $table->id();
            $table->string('assessment_number', 50)->unique();
            $table->foreignId('project_id')->constrained('mar_mangrove_restoration_projects')->cascadeOnDelete();
            $table->date('survey_date');
            $table->decimal('above_ground_biomass_tonnes_per_ha', 8, 2);
            $table->decimal('below_ground_biomass_tonnes_per_ha', 8, 2);
            $table->decimal('soil_organic_carbon_tonnes_per_ha', 8, 2); // Mangrove sediment depth 1m
            $table->decimal('total_carbon_stock_tco2e_per_ha', 8, 2);
            $table->decimal('annual_sequestration_rate_tco2e_yr', 8, 2);
            $table->decimal('buffer_pool_deduction_pct', 5, 2)->default(15.0); // Non-permanence risk buffer 15%
            $table->decimal('net_creditable_carbon_tonnes_co2e', 10, 2);
            $table->string('third_party_auditor_name', 120);
            $table->timestamps();
        });

        // 18. Penerbitan Sertifikat Kredit Karbon Biru (Blue Carbon Credit Issuances)
        Schema::create('mar_blue_carbon_credit_issuances', function (Blueprint $table): void {
            $table->id();
            $table->string('serial_number', 64)->unique();
            $table->foreignId('assessment_id')->constrained('mar_blue_carbon_assessments')->cascadeOnDelete();
            $table->unsignedInteger('vintage_year');
            $table->decimal('carbon_credits_tco2e', 10, 2);
            $table->unsignedBigInteger('unit_credit_price_minor_cents');
            $table->string('credit_standard', 30)->default('VERRA_VM0033'); // VERRA_VM0033, PLAN_VIVO, GOLD_STANDARD
            $table->string('esg_credit_registry_reference', 64)->unique('mar_bcc_esg_ref_unique');
            $table->string('status', 20)->default('ISSUED'); // ISSUED, COMMITTED_OFFSITE, RETIRED
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        // 19. Sertifikasi Uji Tuntas Bebas Deforestasi EUDR (EUDR Deforestation Due Diligence)
        Schema::create('mar_eudr_deforestation_certifications', function (Blueprint $table): void {
            $table->id();
            $table->string('due_diligence_statement_number', 60)->unique('mar_eudr_dds_unique');
            $table->foreignId('batch_id')->constrained('mar_aquaculture_batches')->cascadeOnDelete();
            $table->decimal('pond_polygon_center_lat', 10, 7);
            $table->decimal('pond_polygon_center_lng', 10, 7);
            $table->json('geolocation_polygon_geojson'); // Wajib poligon GPS perimeter tambak
            $table->date('cutoff_baseline_date')->default('2020-12-31'); // Standar EUDR cut-off 31 Des 2020
            $table->boolean('zero_deforestation_verified')->default(true);
            $table->boolean('indigenous_customary_rights_respected')->default(true);
            $table->string('satellite_canopy_sensor', 40)->default('SENTINEL_2_OPTICAL_10M');
            $table->string('certification_verdict', 20)->default('EUDR_COMPLIANT');
            $table->timestamps();
        });

        // 20. Buku Besar Keuangan Ekspor Akuakultur Double-Entry (Marine Ledger Entries)
        Schema::create('mar_aquaculture_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_code', 50)->unique();
            $table->foreignId('export_order_id')->nullable()->constrained('mar_export_orders')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('mar_aquaculture_batches')->nullOnDelete();
            $table->string('account_code', 64)->index();
            $table->string('entry_type', 10); // DEBIT, CREDIT
            $table->unsignedBigInteger('amount_minor_cents');
            $table->string('currency', 4)->default('USD');
            $table->string('reference_type', 40);
            $table->unsignedBigInteger('reference_id');
            $table->string('description', 255);
            $table->string('paired_entry_code', 50)->nullable()->index();
            $table->string('checksum_sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mar_aquaculture_ledger_entries');
        Schema::dropIfExists('mar_eudr_deforestation_certifications');
        Schema::dropIfExists('mar_blue_carbon_credit_issuances');
        Schema::dropIfExists('mar_blue_carbon_assessments');
        Schema::dropIfExists('mar_mangrove_restoration_projects');
        Schema::dropIfExists('mar_escrow_settlements');
        Schema::dropIfExists('mar_cold_chain_telemetry_logs');
        Schema::dropIfExists('mar_export_shipments');
        Schema::dropIfExists('mar_export_orders');
        Schema::dropIfExists('mar_traceability_lot_nodes');
        Schema::dropIfExists('mar_laboratory_assays');
        Schema::dropIfExists('mar_processing_packagings');
        Schema::dropIfExists('mar_export_lots');
        Schema::dropIfExists('mar_harvest_operations');
        Schema::dropIfExists('mar_disease_health_screenings');
        Schema::dropIfExists('mar_feeding_events');
        Schema::dropIfExists('mar_feed_inventory_batches');
        Schema::dropIfExists('mar_water_quality_telemetries');
        Schema::dropIfExists('mar_aquaculture_batches');
        Schema::dropIfExists('mar_hatcheries');
    }
};
