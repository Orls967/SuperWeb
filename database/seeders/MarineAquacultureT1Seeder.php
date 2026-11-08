<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Marine\Domain\Algorithms\BlueCarbonCreditEstimator;
use Modules\Marine\Domain\Algorithms\SeafoodLotMerkleProofGenerator;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\Models\AquacultureEscrowSettlement;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\AquacultureExportOrder;
use Modules\Marine\Domain\Models\AquacultureHarvestOperation;
use Modules\Marine\Domain\Models\AquacultureHatchery;
use Modules\Marine\Domain\Models\BlueCarbonAssessment;
use Modules\Marine\Domain\Models\BlueCarbonCreditIssuance;
use Modules\Marine\Domain\Models\ColdChainTelemetryLog;
use Modules\Marine\Domain\Models\DiseaseHealthScreening;
use Modules\Marine\Domain\Models\EudrDeforestationCertification;
use Modules\Marine\Domain\Models\ExportReeferShipment;
use Modules\Marine\Domain\Models\FeedingEvent;
use Modules\Marine\Domain\Models\FeedInventoryBatch;
use Modules\Marine\Domain\Models\LaboratoryAssay;
use Modules\Marine\Domain\Models\MangroveRestorationProject;
use Modules\Marine\Domain\Models\MarineAquacultureLedgerEntry;
use Modules\Marine\Domain\Models\MarinePond;
use Modules\Marine\Domain\Models\ProcessingPackaging;
use Modules\Marine\Domain\Models\TraceabilityLotNode;
use Modules\Marine\Domain\Models\WaterQualityTelemetry;

class MarineAquacultureT1Seeder extends Seeder
{
    public function run(): void
    {
        // 0. User & Pond
        $farmer = User::firstOrCreate(
            ['email' => 'petani_tarakan@example.com'],
            ['name' => 'Pak Joko Tambak Tarakan', 'password' => bcrypt('password'), 'role' => 'farmer']
        );

        $buyer = User::firstOrCreate(
            ['email' => 'buyer_us_seafood@example.com'],
            ['name' => 'Pacific Catch Seafood LLC (Los Angeles)', 'password' => bcrypt('password'), 'role' => 'buyer']
        );

        $pond = MarinePond::firstOrCreate(
            ['code' => 'POND-TRK-01'],
            [
                'name' => 'Tambak Vannamei Blok Delta A1',
                'farmer_id' => $farmer->id,
                'capacity_units' => 200000,
                'location' => 'Delta Sungai Sesayap, Tarakan, Kalimantan Utara',
            ]
        );

        // 1. Hatchery SPF
        $hatchery = AquacultureHatchery::firstOrCreate(
            ['hatchery_code' => 'HAT-BIM-01'],
            [
                'name' => 'PT Benur Indah Makmur (SPF Center)',
                'operator_name' => 'PT Benur Prima Nusantara',
                'province' => 'Jawa Timur',
                'spf_certificate_number' => 'SPF-CERT-ID-2026-9901',
                'broodstock_origin' => 'Kona Bay Hawaii (Specific Pathogen Free)',
                'biosecurity_level' => 'LEVEL_3',
                'certified_at' => '2026-01-10',
                'expires_at' => '2027-01-10',
                'status' => 'ACTIVE',
            ]
        );

        // 2. Batch Tambak
        $batch = AquacultureBatch::firstOrCreate(
            ['batch_code' => 'BATCH-VNM-2026-01'],
            [
                'pond_id' => $pond->id,
                'hatchery_id' => $hatchery->id,
                'species_scientific_name' => 'Litopenaeus vannamei',
                'commercial_name' => 'Pacific Whiteleg Shrimp',
                'initial_stock_count' => 150000,
                'initial_density_per_m2' => 30.0,
                'initial_average_body_weight_grams' => 0.002,
                'stocking_date' => now()->subDays(85)->toDateString(),
                'expected_harvest_date' => now()->subDays(2)->toDateString(),
                'target_harvest_weight_grams' => 25.0,
                'certification_scope' => 'ASC_BAP',
                'stage' => 'HARVESTED',
                'status' => 'ACTIVE',
            ]
        );

        // 3. Telemetri Kualitas Air IoT
        WaterQualityTelemetry::firstOrCreate(
            ['batch_id' => $batch->id, 'sensor_device_uuid' => 'IOT-SONDE-POND-A1'],
            [
                'recorded_at' => now()->subDays(5)->toDateTimeString(),
                'dissolved_oxygen_mg_l' => 5.80,
                'ph_level' => 7.85,
                'water_temperature_celsius' => 29.20,
                'salinity_ppt' => 22.50,
                'total_ammonia_nitrogen_ppm' => 0.060,
                'nitrite_no2_ppm' => 0.015,
                'alkalinity_mg_l' => 140.00,
                'oxidation_reduction_potential_mv' => 240.0,
                'aerator_active' => true,
                'water_quality_grade' => 'OPTIMAL',
            ]
        );

        // 4. Inventori Pakan & Feeding Event
        $feed = FeedInventoryBatch::firstOrCreate(
            ['feed_lot_number' => 'FEED-GRO-2026-A'],
            [
                'manufacturer_name' => 'PT Japfa Aquacare Indonesia',
                'feed_product_name' => 'Vannamei Growout Pellets 2.0mm',
                'protein_percentage' => 38.0,
                'lipid_percentage' => 7.5,
                'marine_raw_materials_sustainable' => true,
                'feed_certification' => 'ASC_FEED_STANDARD',
                'quantity_kg' => 10000,
                'remaining_kg' => 7500,
                'unit_price_minor_cents' => 150, // $1.50/kg
                'manufactured_at' => now()->subMonths(2)->toDateString(),
                'expires_at' => now()->addMonths(10)->toDateString(),
            ]
        );

        FeedingEvent::firstOrCreate(
            ['batch_id' => $batch->id, 'feed_inventory_batch_id' => $feed->id],
            [
                'feeding_date' => now()->subDays(5)->toDateString(),
                'feeding_tray_number' => 1,
                'quantity_dispensed_kg' => 60.0,
                'estimated_feed_consumption_pct' => 98.0,
                'daily_sampling_abw_grams' => 24.5,
                'daily_fcr_estimate' => 1.250,
                'feeding_technique' => 'AUTO_FEEDER_ACOUSTIC',
            ]
        );

        // 5. Skrining Penyakit (Semua Negatif)
        DiseaseHealthScreening::firstOrCreate(
            ['batch_id' => $batch->id, 'sample_barcode' => 'SAMP-PCR-TRK-001'],
            [
                'screening_date' => now()->subDays(10)->toDateString(),
                'screening_agency' => 'Balai Karantina Ikan Pengendalian Mutu (BKIPM)',
                'wssv_pcr_negative' => true,
                'imnv_pcr_negative' => true,
                'ehp_pcr_negative' => true,
                'ahpnd_pcr_negative' => true,
                'vibrio_parahaemolyticus_controlled' => true,
                'hepatopancreas_condition' => 'HEALTHY_LIPIDS_FULL',
                'quarantine_status' => 'CLEARED',
            ]
        );

        // 6. Operasi Panen
        $harvest = AquacultureHarvestOperation::firstOrCreate(
            ['harvest_code' => 'HRV-2026-A1-001'],
            [
                'batch_id' => $batch->id,
                'harvested_at' => now()->subDays(2)->toDateTimeString(),
                'harvest_method' => 'DRAIN_HARVEST_LIVE_PUMP',
                'slurry_ice_temperature_celsius' => -1.5,
                'gross_biomass_harvested_kg' => 3300.0,
                'survival_rate_percentage' => 88.0,
                'final_fcr' => 1.250,
                'average_count_per_kg' => 40,
                'chain_of_custody_cert' => 'ASC-C-02881',
                'lead_technician_name' => 'Ir. Hendra Gunawan',
                'status' => 'COMPLETED',
            ]
        );

        // 7. Lot Hasil Panen Ekspor (HLSO Rendemen 66%)
        $exportLot = AquacultureExportLot::firstOrCreate(
            ['lot_tracking_number' => 'LOT-EXP-2026-USA-001'],
            [
                'harvest_operation_id' => $harvest->id,
                'product_form' => 'HEADLESS_SHELL_ON',
                'commercial_grade' => 'GRADE_A1',
                'size_classification' => '31_40',
                'net_lot_weight_kg' => 2178.0,
                'pack_count_cartons' => 218,
                'defects_organoleptic_pct' => 0.40,
                'estimated_export_value_minor_cents' => 2500000, // $25,000 USD
                'freeze_method' => 'INDIVIDUALLY_QUICK_FROZEN',
                'storage_room_code' => 'FREEZER_ROOM_B3',
            ]
        );

        // 8. Pemrosesan & Pengemasan
        ProcessingPackaging::firstOrCreate(
            ['export_lot_id' => $exportLot->id],
            [
                'packaging_slip_number' => 'SLIP-PK-2026-001',
                'processing_plant_code' => 'ID-PLANT-042',
                'haccp_plan_reference' => 'HACCP-REV-08',
                'core_temperature_celsius' => -22.5,
                'glazing_water_percentage' => 8.0,
                'barcode_gtin14' => '08991234567890',
                'packaging_material' => 'FOOD_GRADE_CORRUGATED_WAXED',
                'packed_at' => now()->subDay()->toDateString(),
                'best_before_date' => now()->addMonths(24)->toDateString(),
                'sanitation_inspector_name' => 'Dra. Endang Lestari',
            ]
        );

        // 9. Uji Laboratorium (SPS Assay)
        LaboratoryAssay::firstOrCreate(
            ['export_lot_id' => $exportLot->id],
            [
                'assay_certificate_number' => 'ASSAY-CERT-2026-001',
                'accredited_laboratory_name' => 'Balai Uji Standar Karantina Ikan BUSKIPM',
                'tested_at' => now()->subDay()->toDateString(),
                'chloramphenicol_ppb' => 0.000,
                'nitrofuran_aoz_ppb' => 0.000,
                'nitrofuran_amoz_ppb' => 0.000,
                'fluoroquinolones_ppb' => 0.000,
                'malachite_green_ppb' => 0.000,
                'histamine_ppm' => 3.50,
                'heavy_metal_cadmium_ppm' => 0.020,
                'heavy_metal_lead_ppm' => 0.015,
                'microbiology_salmonella_cfu_25g' => 0.0,
                'overall_compliance_verdict' => 'PASSED_FOR_EXPORT',
                'signed_analyst_name' => 'Ahmad Fauzi, S.Si.',
            ]
        );

        // 10. Merkle Tree Traceability Node
        $merkleGen = new SeafoodLotMerkleProofGenerator;
        $leafEvents = [
            'hatchery' => hash('sha256', $hatchery->spf_certificate_number),
            'feed' => hash('sha256', $feed->feed_lot_number),
            'water' => hash('sha256', (string) $batch->id),
            'harvest' => hash('sha256', $harvest->harvest_code),
            'assay' => hash('sha256', 'ASSAY-CERT-2026-001'),
        ];
        $combinedLeafHash = hash('sha256', implode('', $leafEvents));
        $rootHash = hash('sha256', $combinedLeafHash.'CANONICAL_ROOT');

        TraceabilityLotNode::firstOrCreate(
            ['export_lot_id' => $exportLot->id, 'leaf_index' => 0],
            [
                'node_uuid' => 'NODE-UUID-TRK-001',
                'hatchery_data_hash' => $leafEvents['hatchery'],
                'feed_cycle_hash' => $leafEvents['feed'],
                'water_quality_hash' => $leafEvents['water'],
                'harvest_event_hash' => $leafEvents['harvest'],
                'assay_lab_hash' => $leafEvents['assay'],
                'combined_leaf_hash' => $combinedLeafHash,
                'merkle_root_hash' => $rootHash,
                'merkle_proof_path' => [['position' => 'right', 'hash' => hash('sha256', 'SIBLING_01')]],
                'anchored_at' => now()->toDateTimeString(),
            ]
        );

        // 11. International Export Order
        $order = AquacultureExportOrder::firstOrCreate(
            ['order_number' => 'ORD-EXP-USA-2026-88'],
            [
                'buyer_party_id' => $buyer->id,
                'destination_country_code' => 'US',
                'destination_port_unlocode' => 'USLAX',
                'incoterm' => 'CIF',
                'total_ordered_weight_kg' => 2178.0,
                'total_contract_value_minor_cents' => 2500000,
                'payment_instrument' => 'LETTER_OF_CREDIT',
                'letter_of_credit_number' => 'LC-CITI-NY-2026-0991',
                'issuing_bank_swift' => 'CITIUS33XXX',
                'status' => 'CONFIRMED',
            ]
        );

        // 12. Reefer Container Shipment & Cold Chain Telemetry
        $shipment = ExportReeferShipment::firstOrCreate(
            ['shipment_tracking_number' => 'REEF-EXP-2026-001'],
            [
                'export_order_id' => $order->id,
                'export_lot_id' => $exportLot->id,
                'container_iso_number' => 'MSKU9021882',
                'shipping_line_carrier' => 'Maersk Line',
                'booking_reference' => 'BKG-MAERSK-SUB-01',
                'ocean_bill_of_lading' => 'MAEU192837465',
                'health_certificate_official_number' => 'HC-KKP-ID-2026-009182',
                'temperature_setpoint_celsius' => -22.0,
                'stuffed_and_sealed_at' => now()->subHours(12)->toDateTimeString(),
                'estimated_departure_at' => now()->addDays(2)->toDateTimeString(),
                'estimated_arrival_at' => now()->addDays(22)->toDateTimeString(),
                'shipment_status' => 'HEALTH_CERT_ISSUED',
            ]
        );

        ColdChainTelemetryLog::firstOrCreate(
            ['export_shipment_id' => $shipment->id],
            [
                'recorded_at' => now()->toDateTimeString(),
                'ambient_temperature_celsius' => 28.5,
                'supply_air_temperature_celsius' => -22.4,
                'return_air_temperature_celsius' => -21.8,
                'cargo_probe_temperature_celsius' => -22.0,
                'humidity_percentage' => 85.0,
                'latitude' => -7.2001000,
                'longitude' => 112.7302000,
                'compressor_running' => true,
                'defrost_cycle_active' => false,
                'temperature_breach_detected' => false,
            ]
        );

        // 13. Escrow Settlement
        AquacultureEscrowSettlement::firstOrCreate(
            ['settlement_code' => 'ESC-SETTLE-2026-001'],
            [
                'export_order_id' => $order->id,
                'farmer_id' => $farmer->id,
                'gross_harvest_value_minor_cents' => 2500000,
                'feed_deduction_minor_cents' => 450000,
                'hatchery_seed_deduction_minor_cents' => 150000,
                'quality_grade_premium_minor_cents' => 100000,
                'net_farmer_payout_minor_cents' => 2000000,
                'escrow_hold_reference' => 'ESC-HOLD-K11-TRK-001',
                'released_at' => null,
                'status' => 'HELD',
            ]
        );

        // 14. Proyek Restorasi Mangrove (Verra VM0033) & Blue Carbon Credit
        $mangrove = MangroveRestorationProject::firstOrCreate(
            ['project_code' => 'MNG-TRK-01'],
            [
                'project_name' => 'Proyek Silvofishery Mangrove Delta Tarakan',
                'coastal_regency' => 'Tarakan',
                'restoration_area_hectares' => 60.0,
                'dominant_mangrove_species' => 'Rhizophora mucronata',
                'trees_planted_count' => 120000,
                'survival_rate_percentage' => 88.5,
                'initiated_at' => '2023-01-15',
                'verra_registry_code' => 'VCS-PROJECT-ID-3391',
                'status' => 'VERIFIED_CREDIT',
            ]
        );

        $carbonEstimator = new BlueCarbonCreditEstimator;
        $calc = $carbonEstimator->estimateBlueCarbon('Rhizophora', 60.0, 14.0, 1700);

        $assessment = BlueCarbonAssessment::firstOrCreate(
            ['assessment_number' => 'ASSESS-TRK-2026-01'],
            [
                'project_id' => $mangrove->id,
                'survey_date' => now()->subMonth()->toDateString(),
                'above_ground_biomass_tonnes_per_ha' => $calc['aboveground_biomass_t_per_ha'],
                'below_ground_biomass_tonnes_per_ha' => $calc['belowground_biomass_t_per_ha'],
                'soil_organic_carbon_tonnes_per_ha' => $calc['soil_carbon_t_per_ha'],
                'total_carbon_stock_tco2e_per_ha' => $calc['total_carbon_stock_t_per_ha'],
                'annual_sequestration_rate_tco2e_yr' => 12.5,
                'buffer_pool_deduction_pct' => 15.0,
                'net_creditable_carbon_tonnes_co2e' => $calc['net_marketable_carbon_credits_tco2e'],
                'third_party_auditor_name' => 'PT Sucofindo Verra Certified Auditor',
            ]
        );

        BlueCarbonCreditIssuance::firstOrCreate(
            ['serial_number' => 'VCS-2026-MNG-TRK-9901'],
            [
                'assessment_id' => $assessment->id,
                'vintage_year' => 2026,
                'carbon_credits_tco2e' => $calc['net_marketable_carbon_credits_tco2e'],
                'unit_credit_price_minor_cents' => 1200, // $12.00 / tCO2e
                'credit_standard' => 'VERRA_VM0033',
                'esg_credit_registry_reference' => 'VERRA-SERIAL-2026-00918',
                'status' => 'ISSUED',
                'issued_at' => now()->toDateTimeString(),
            ]
        );

        // 15. EUDR Due Diligence Certification
        EudrDeforestationCertification::firstOrCreate(
            ['batch_id' => $batch->id],
            [
                'due_diligence_statement_number' => 'DDS-EUDR-2026-TRK-001',
                'pond_polygon_center_lat' => 3.3211000,
                'pond_polygon_center_lng' => 117.5921000,
                'geolocation_polygon_geojson' => [
                    'type' => 'Polygon',
                    'coordinates' => [
                        [[117.5921, 3.3211], [117.5945, 3.3215], [117.5942, 3.3189], [117.5918, 3.3185], [117.5921, 3.3211]],
                    ],
                ],
                'cutoff_baseline_date' => '2020-12-31',
                'zero_deforestation_verified' => true,
                'indigenous_customary_rights_respected' => true,
                'satellite_canopy_sensor' => 'SENTINEL_2_OPTICAL_10M',
                'certification_verdict' => 'EUDR_COMPLIANT',
            ]
        );

        // 16. Double-Entry Marine Ledger
        $entryCode = 'JRN-AQUA-2026-001';
        $accountCode = '1150-AQUACULTURE-INVENTORY';
        $entryType = 'CREDIT';
        $amountMinor = 2500000;
        $currency = 'USD';
        $desc = 'Pelepasan ekspor komoditas udang lot '.$exportLot->lot_tracking_number;
        $checksum = hash('sha256', $entryCode.'|'.$accountCode.'|'.$entryType.'|'.$amountMinor.'|'.$currency.'|'.$desc);

        MarineAquacultureLedgerEntry::firstOrCreate(
            ['entry_code' => $entryCode],
            [
                'export_order_id' => $order->id,
                'batch_id' => $batch->id,
                'account_code' => $accountCode,
                'entry_type' => $entryType,
                'amount_minor_cents' => $amountMinor,
                'currency' => $currency,
                'reference_type' => 'EXPORT_ORDER',
                'reference_id' => $order->id,
                'description' => $desc,
                'paired_entry_code' => 'JRN-AQUA-2026-002',
                'checksum_sha256' => $checksum,
            ]
        );
    }
}
