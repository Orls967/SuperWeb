<?php

declare(strict_types=1);

namespace Modules\Egy\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Egy\Application\Services\EnergyGridAndMeteringService;
use Modules\Egy\Domain\Models\GenerationAsset;
use Modules\Egy\Domain\Models\GridNode;
use Modules\Egy\Domain\Models\PpaContract;
use Modules\Egy\Domain\Models\SmartMeter;

/**
 * Seeder Deterministik T1 untuk pengujian skala penuh Fase 126.
 * Berisi 12 unit pembangkit genco fleet, grid nodes, PPA contracts, dan smart meters AMI.
 */
class EgyT1Seeder extends Seeder
{
    public function run(): void
    {
        (new EgyRbacSeeder)->run();

        // 1. Grid Nodes (Trafo Kawasan)
        $substations = [
            ['code' => 'NODE-WEST-SUB01', 'name' => 'Substasi Barat Cirata 150kV', 'region' => 'WEST_REGION', 'kv' => 150.0, 'peak_mw' => 500.0],
            ['code' => 'NODE-EAST-SUB02', 'name' => 'Substasi Timur Bekapur 150kV', 'region' => 'EAST_REGION', 'kv' => 150.0, 'peak_mw' => 600.0],
            ['code' => 'NODE-CBD-SUB03', 'name' => 'Substasi Pusat Sudirman 20kV', 'region' => 'CENTRAL_REGION', 'kv' => 20.0, 'peak_mw' => 250.0],
        ];

        foreach ($substations as $s) {
            GridNode::firstOrCreate(
                ['node_code' => $s['code']],
                [
                    'name' => $s['name'],
                    'substation_region' => $s['region'],
                    'nominal_voltage_kv' => $s['kv'],
                    'current_load_mw' => 0.0,
                    'peak_capacity_mw' => $s['peak_mw'],
                ]
            );
        }

        // 2. Genco Fleet (12 Pembangkit Multi-Teknologi)
        $fleet = [
            ['code' => 'GEN-SOL-01', 'name' => 'Cirata Floating Solar Phase 1', 'fuel' => 'solar', 'type' => 'SOLAR_FARM', 'cap' => 145, 'min' => 0, 'cost' => 0, 'must_run' => false],
            ['code' => 'GEN-SOL-02', 'name' => 'Karangkates Rooftop Array', 'fuel' => 'solar', 'type' => 'SOLAR_FARM', 'cap' => 60, 'min' => 0, 'cost' => 0, 'must_run' => false],
            ['code' => 'GEN-HYD-01', 'name' => 'Saguling Hydro Peaker', 'fuel' => 'hydro', 'type' => 'HYDRO_PEAKER', 'cap' => 350, 'min' => 50, 'cost' => 150, 'must_run' => false],
            ['code' => 'GEN-GEO-01', 'name' => 'Kamojang Geothermal Baseload', 'fuel' => 'geothermal', 'type' => 'GEOTHERMAL_BASE', 'cap' => 235, 'min' => 200, 'cost' => 450, 'must_run' => true],
            ['code' => 'GEN-COA-01', 'name' => 'Suralaya Supercritical Unit 8', 'fuel' => 'coal', 'type' => 'THERMAL_PLTU', 'cap' => 600, 'min' => 300, 'cost' => 650, 'must_run' => true],
            ['code' => 'GEN-COA-02', 'name' => 'Paiton Unit 9 Ultra-Supercritical', 'fuel' => 'coal', 'type' => 'THERMAL_PLTU', 'cap' => 660, 'min' => 350, 'cost' => 680, 'must_run' => true],
            ['code' => 'GEN-COA-03', 'name' => 'Jawa-7 Coal Unit 1', 'fuel' => 'coal', 'type' => 'THERMAL_PLTU', 'cap' => 1000, 'min' => 500, 'cost' => 700, 'must_run' => true],
            ['code' => 'GEN-GAS-01', 'name' => 'Muara Karang Combined Cycle 1', 'fuel' => 'gas', 'type' => 'THERMAL_PLTGU', 'cap' => 500, 'min' => 150, 'cost' => 950, 'must_run' => false],
            ['code' => 'GEN-GAS-02', 'name' => 'Tanjung Priok Gas Turbine 2', 'fuel' => 'gas', 'type' => 'THERMAL_PLTGU', 'cap' => 450, 'min' => 120, 'cost' => 980, 'must_run' => false],
            ['code' => 'GEN-BES-01', 'name' => 'Cikarang BESS Grid Storage 100MWh', 'fuel' => 'battery', 'type' => 'BATTERY_BESS', 'cap' => 100, 'min' => 0, 'cost' => 1200, 'must_run' => false],
            ['code' => 'GEN-DSL-01', 'name' => 'Pesanggaran Fast Peaker Genset 1', 'fuel' => 'diesel', 'type' => 'GENSET', 'cap' => 100, 'min' => 20, 'cost' => 2100, 'must_run' => false],
            ['code' => 'GEN-DSL-02', 'name' => 'Pesanggaran Fast Peaker Genset 2', 'fuel' => 'diesel', 'type' => 'GENSET', 'cap' => 100, 'min' => 20, 'cost' => 2200, 'must_run' => false],
        ];

        foreach ($fleet as $f) {
            GenerationAsset::firstOrCreate(
                ['asset_code' => $f['code']],
                [
                    'name' => $f['name'],
                    'fuel_type' => $f['fuel'],
                    'asset_type' => $f['type'],
                    'capacity_mw' => $f['cap'],
                    'available_capacity_mw' => $f['cap'],
                    'installed_capacity_mw' => (float) $f['cap'],
                    'min_stable_mw' => $f['min'],
                    'marginal_cost_idr' => $f['cost'],
                    'marginal_cost_per_mwh_minor' => $f['cost'] * 1000,
                    'is_must_run' => $f['must_run'],
                    'status' => 'ONLINE',
                ]
            );
        }

        // 3. Smart Meters (AMI)
        for ($i = 1; $i <= 10; $i++) {
            $code = sprintf('MTR-AMI-%03d', $i);
            $propId = sprintf('PROP-COMMERCIAL-%02d', $i);
            SmartMeter::firstOrCreate(
                ['meter_code' => $code],
                [
                    'meter_serial_number' => $code,
                    'utility_type' => 'electricity',
                    'property_id' => $propId,
                    'consumer_property_id' => $propId,
                    'consumer_property_type' => 'COMMERCIAL',
                    'node_id' => 'NODE-CBD-SUB03',
                    'customer_party_id' => "CUST-{$i}",
                    'tariff_category' => 'BUSINESS_TOU',
                    'is_active' => true,
                    'status' => 'ACTIVE',
                ]
            );
        }

        // 4. Sample Telemetry Ingestion & Billing Simulation
        $service = app(EnergyGridAndMeteringService::class);
        $meter1 = SmartMeter::where('meter_code', 'MTR-AMI-001')->first();
        if ($meter1) {
            $now = Carbon::now()->startOfHour();
            for ($h = 0; $h < 4; $h++) {
                $start = $now->copy()->subHours(4 - $h);
                $end = $start->copy()->addMinutes(15);
                $service->ingestReading(
                    meterId: (string) $meter1->id,
                    intervalStart: $start->toDateTimeString(),
                    intervalEnd: $end->toDateTimeString(),
                    consumptionKwh: 50 + ($h * 10),
                    productionKwh: 0,
                    peakKw: 30,
                    idempotencyKey: "SEED-INGEST-{$meter1->id}-H{$h}"
                );
            }

            $service->generateBilling(
                propertyId: (string) $meter1->property_id,
                period: $now->format('Y-m'),
                idempotencyKey: "SEED-BILL-{$meter1->property_id}-".$now->format('Ym')
            );
        }

        // 5. PPA Contract Simulation
        PpaContract::firstOrCreate(
            ['contract_number' => 'PPA-T1-CIRATA-001'],
            [
                'producer_entity_id' => 'GEN-CIRATA-SOLAR',
                'grid_offtaker_id' => 'GRID-SUPERWEB-CBD',
                'generator_party_id' => 'GEN-CIRATA-SOLAR',
                'offtaker_party_id' => 'GRID-SUPERWEB-CBD',
                'capacity_kw' => 145000,
                'rate_per_kwh_idr' => 850,
                'feed_in_tariff_per_kwh_minor' => 85000.0,
                'surplus_kwh_exported' => 0.0,
                'status' => 'active',
                'valid_from' => '2026-01-01',
                'valid_to' => '2035-12-31',
            ]
        );
    }
}
