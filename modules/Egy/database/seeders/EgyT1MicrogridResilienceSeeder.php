<?php

declare(strict_types=1);

namespace Modules\Egy\database\seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Banking\Domain\Enums\AccountKind;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Modules\Egy\Domain\Models\BessAsset;
use Modules\Egy\Domain\Models\Microgrid;
use Modules\Egy\Domain\Models\MicrogridFeeder;

final class EgyT1MicrogridResilienceSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(MicrogridAndResilienceService::class);

        // Pastikan akun-akun ledger penting terdaftar
        LedgerAccount::firstOrCreate(
            ['code' => 'egy:bess_arbitrage_revenue:IDR'],
            [
                'name' => 'BESS Arbitrage Revenue',
                'kind' => AccountKind::REVENUE,
                'asset_code' => 'IDR',
                'allow_negative' => false,
            ]
        );
        LedgerAccount::firstOrCreate(
            ['code' => 'egy:bess_energy_cost:IDR'],
            [
                'name' => 'BESS Energy Cost',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
        LedgerAccount::firstOrCreate(
            ['code' => 'egy:bess_degradation_expense:IDR'],
            [
                'name' => 'BESS Degradation Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
        LedgerAccount::firstOrCreate(
            ['code' => 'egy:bess_replacement_reserve:IDR'],
            [
                'name' => 'BESS Replacement Reserve',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
        LedgerAccount::firstOrCreate(
            ['code' => 'egy:genset_fuel_expense:IDR'],
            [
                'name' => 'Genset Fuel Expense',
                'kind' => AccountKind::EXPENSE,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
        LedgerAccount::firstOrCreate(
            ['code' => 'ap:egy:fuel_supplier:PERTAMINA:IDR'],
            [
                'name' => 'AP Fuel Supplier PERTAMINA',
                'kind' => AccountKind::LIABILITY,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
        LedgerAccount::firstOrCreate(
            ['code' => 'ar:egy:customer:1:IDR'],
            [
                'name' => 'AR Clearing Internal',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );
        LedgerAccount::firstOrCreate(
            ['code' => 'ar:egy:customer:PLN:IDR'],
            [
                'name' => 'AR Grid Operator PLN',
                'kind' => AccountKind::ASSET,
                'asset_code' => 'IDR',
                'allow_negative' => true,
            ]
        );

        // 1. Buat Microgrid Kawasan Industri Cikarang
        $microgrid = Microgrid::firstOrCreate(
            ['microgrid_code' => 'MG-CIKARANG-01'],
            [
                'site_name' => 'Kawasan Industri Terpadu Cikarang',
                'operating_mode' => 'grid_connected',
                'total_capacity_kw' => 5000,
                'current_generation_kw' => 5000,
                'current_load_kw' => 3800,
                'grid_frequency_mhz' => 50000,
                'bus_voltage_volt' => 20000,
                'is_black_start_capable' => true,
            ]
        );

        // Tambah Feeder Beban
        MicrogridFeeder::firstOrCreate(
            ['microgrid_id' => $microgrid->id, 'feeder_code' => 'FDR-ICU-01'],
            ['feeder_name' => 'Rumah Sakit Pekerja Kawasan (ICU & Bedah)', 'tier' => 'TIER_1_CRITICAL', 'priority_rank' => 1, 'rated_load_kw' => 450, 'is_energized' => true]
        );
        MicrogridFeeder::firstOrCreate(
            ['microgrid_id' => $microgrid->id, 'feeder_code' => 'FDR-DC-01'],
            ['feeder_name' => 'Edge Data Center & Telemetri', 'tier' => 'TIER_1_CRITICAL', 'priority_rank' => 2, 'rated_load_kw' => 350, 'is_energized' => true]
        );
        MicrogridFeeder::firstOrCreate(
            ['microgrid_id' => $microgrid->id, 'feeder_code' => 'FDR-COLD-01'],
            ['feeder_name' => 'Cold Storage Logistik Pangan', 'tier' => 'TIER_2_ESSENTIAL', 'priority_rank' => 3, 'rated_load_kw' => 600, 'is_energized' => true]
        );
        MicrogridFeeder::firstOrCreate(
            ['microgrid_id' => $microgrid->id, 'feeder_code' => 'FDR-MALL-01'],
            ['feeder_name' => 'Komersial & Restoran Mall', 'tier' => 'TIER_3_COMMERCIAL', 'priority_rank' => 4, 'rated_load_kw' => 1200, 'is_energized' => true]
        );
        MicrogridFeeder::firstOrCreate(
            ['microgrid_id' => $microgrid->id, 'feeder_code' => 'FDR-EV-01'],
            ['feeder_name' => 'Stasiun Pengisian Daya Mobil Listrik', 'tier' => 'TIER_4_FLEXIBLE', 'priority_rank' => 5, 'rated_load_kw' => 800, 'is_energized' => true]
        );

        // 2. Aset BESS (Kapasitas 2.000 kWh)
        $bess = BessAsset::firstOrCreate(
            ['bess_code' => 'BESS-CIKARANG-01'],
            [
                'site_name' => 'BESS Container Park Cikarang',
                'battery_chemistry' => 'LFP',
                'capacity_kwh' => 2000,
                'max_charge_power_kw' => 500,
                'max_discharge_power_kw' => 500,
                'current_soc_bps' => 3000,
                'state_of_health_bps' => 9980,
                'cycle_count' => 45,
                'replacement_cost_idr' => 5_000_000_000,
                'status' => 'operational',
            ]
        );

        // 3. Jalankan Arbitrase BESS
        $hourlyTariff = [
            900, 900, 900, 900, 900, 900,
            1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450, 1450,
            2200, 2200, 2200, 2200, 2200, 2200,
            900,
        ];
        $service->dispatchBessArbitrage(
            bessAssetId: $bess->id,
            hourlyTariffProfile: $hourlyTariff,
            dispatchDate: '2026-11-06',
            idempotencyKey: 'SEED-BESS-DISPATCH-20261106'
        );

        // 4. Catat Uji Beban Mingguan Genset Diesel
        $service->recordGeneratorTest(
            generatorAssetCode: 'GENSET-CUMMINS-01',
            siteName: 'Genset Standby Central Cikarang',
            runType: 'weekly_load_test',
            startTime: Carbon::parse('2026-11-06 09:00:00'),
            durationMinutes: 60,
            fuelConsumedLiters: 120,
            loadPercentageBps: 5500, // 55% load
            notes: 'Uji periodik mingguan lulus tanpa getaran abnormal.'
        );

        // 5. Catat Peristiwa Demand-Response Sinyal PLN
        $service->handleDemandResponseCurtailment(
            eventCode: 'DR-PLN-20261106-01',
            curtailmentSignalId: 'SIG-PLN-WBP-881',
            startTime: Carbon::parse('2026-11-06 18:00:00'),
            targetCurtailmentKw: 500,
            achievedCurtailmentKw: 480,
            responseTimeSeconds: 420 // 7 menit (lolos < 15 menit)
        );

        // 6. Rencana Pemulihan Black-Start
        $service->executeBlackStart(
            microgridId: $microgrid->id,
            components: [
                ['code' => 'CRANK_GENSET', 'name' => 'Diesel Cranking Standby', 'dependencies' => [], 'step_duration_seconds' => 120, 'inrush_current_amp' => 250],
                ['code' => 'BESS_INVERTER', 'name' => 'Grid-Forming Inverter BESS', 'dependencies' => ['CRANK_GENSET'], 'step_duration_seconds' => 60, 'inrush_current_amp' => 80],
                ['code' => 'BUSBAR_MV', 'name' => 'Busbar Primer 20kV', 'dependencies' => ['BESS_INVERTER'], 'step_duration_seconds' => 45, 'inrush_current_amp' => 120],
                ['code' => 'TX_HOSPITAL', 'name' => 'Trafo Step-Down RS', 'dependencies' => ['BUSBAR_MV'], 'step_duration_seconds' => 30, 'inrush_current_amp' => 100],
            ],
            planCode: 'PLAN-BLACKSTART-CIKARANG-01'
        );
    }
}
