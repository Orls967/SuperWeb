<?php

declare(strict_types=1);

namespace Modules\Mining\database\seeders;

use Carbon\CarbonImmutable;
use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\SettleShiftHaulingBillingAction;
use Modules\Mining\Application\Sagas\FuelAnomalyHoldSaga;
use Modules\Mining\Domain\Enums\EquipmentStatus;
use Modules\Mining\Domain\Enums\FuelAnomalyCaseStatus;
use Modules\Mining\Domain\Enums\HaulCycleStatus;
use Modules\Mining\Domain\Enums\MinePlanStatus;
use Modules\Mining\Domain\Models\MiningExternalFactor;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningSite;

/**
 * Seeder Skala T1 Kaya Fase 93 (T-6, U-12):
 * - 3 Entitas Utama (2 IUP domestik + 1 Offtaker Singapura) + 30 Kontraktor Hauling
 * - 5 Site lintas pulau (Kalimantan Timur, Sulawesi Tenggara, Maluku Utara, Sumatera Selatan, Papua)
 * - 40 Pit (8 pit per site), 600 Unit Alat Berat (120 per site)
 * - Riwayat 180 hari rencana shift & siklus angkut
 * - Seluruh 8 status dari ke-4 State Machine (MinePlan, HaulCycle, Equipment, FuelAnomalyCase) muncul
 * - 15 Kasus Unik Bernama (CASE-93-01 s/d CASE-93-15)
 */
class MiningFase93Seeder extends Seeder
{
    public function run(): void
    {
        $this->call(LedgerAccountsSeeder::class);
        $this->call(MiningRbacSeeder::class);

        if (MiningSite::where('site_code', 'SITE-KALTIM-93')->exists()) {
            return;
        }

        $now = CarbonImmutable::parse('2026-11-07 08:00:00', 'UTC');

        // 1. Parameter Eksternal Berversi (T-5, T-8)
        $factors = [
            ['factor_key' => 'weather_rainfall_default', 'numeric_value_scaled' => 20, 'unit' => 'mm', 'effective_from' => '2026-05-01', 'version' => 1, 'source' => 'BMKG Klimatologi Tambang', 'label' => 'asumsi_simulasi'],
            ['factor_key' => 'weather_rainfall_SITE-KALTIM-93', 'numeric_value_scaled' => 65, 'unit' => 'mm', 'effective_from' => '2026-10-01', 'version' => 1, 'source' => 'BMKG Samarinda', 'label' => 'asumsi_simulasi'],
            ['factor_key' => 'hba_coal_usd_per_ton', 'numeric_value_scaled' => 8850, 'unit' => 'usd_cents', 'effective_from' => '2026-05-01', 'version' => 1, 'source' => 'Kepmen ESDM 72.K/MB.01/MEM.B/2025', 'label' => 'regulasi'],
            ['factor_key' => 'hba_coal_usd_per_ton', 'numeric_value_scaled' => 6850, 'unit' => 'usd_cents', 'effective_from' => '2026-09-01', 'version' => 2, 'source' => 'Kepmen ESDM 72.K/MB.01/MEM.B/2025 (HBA Turun Tier)', 'label' => 'regulasi'],
            ['factor_key' => 'usd_idr_exchange_rate', 'numeric_value_scaled' => 16250, 'unit' => 'idr', 'effective_from' => '2026-05-01', 'version' => 1, 'source' => 'JISDOR Bank Indonesia', 'label' => 'asumsi_simulasi'],
            ['factor_key' => 'fuel_anomaly_threshold_bps', 'numeric_value_scaled' => 1500, 'unit' => 'bps', 'effective_from' => '2026-05-01', 'version' => 1, 'source' => 'SOP Fleet Management', 'label' => 'fixture'],
        ];
        foreach ($factors as $f) {
            MiningExternalFactor::create($f);
        }

        // 2. 5 Site Lintas Pulau
        $siteConfigs = [
            ['code' => 'SITE-KALTIM-93', 'name' => 'IUP Kutai Timur Batubara Utama', 'commodity' => 'COAL', 'location' => 'Kutai Timur, Kalimantan Timur', 'tz' => 'Asia/Makassar'],
            ['code' => 'SITE-SULTRA-93', 'name' => 'IUP Konawe Utara Nikel Laterit', 'commodity' => 'NICKEL', 'location' => 'Konawe Utara, Sulawesi Tenggara', 'tz' => 'Asia/Makassar'],
            ['code' => 'SITE-MALUT-93', 'name' => 'IUP Halmahera Timur Saprolite', 'commodity' => 'NICKEL', 'location' => 'Halmahera Timur, Maluku Utara', 'tz' => 'Asia/Jayapura'],
            ['code' => 'SITE-SUMSEL-93', 'name' => 'IUP Muara Enim Antrasit', 'commodity' => 'COAL', 'location' => 'Muara Enim, Sumatera Selatan', 'tz' => 'Asia/Jakarta'],
            ['code' => 'SITE-PAPUA-93', 'name' => 'IUP Mimika Tembaga Emas', 'commodity' => 'COPPER', 'location' => 'Mimika, Papua Tengah', 'tz' => 'Asia/Jayapura'],
        ];

        $siteIds = [];
        $pitIdsBySite = [];
        $pitRows = [];

        foreach ($siteConfigs as $sIdx => $sc) {
            $siteId = (string) Str::uuid();
            $siteIds[$sc['code']] = $siteId;

            DB::table('min_sites')->insert([
                'id' => $siteId,
                'site_code' => $sc['code'],
                'name' => $sc['name'],
                'commodity' => $sc['commodity'],
                'location' => $sc['location'],
                'timezone' => $sc['tz'],
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $pitIdsBySite[$siteId] = [];
            for ($p = 1; $p <= 8; $p++) {
                $pitId = (string) Str::uuid();
                $pitIdsBySite[$siteId][] = $pitId;
                $pitRows[] = [
                    'id' => $pitId,
                    'site_id' => $siteId,
                    'pit_code' => sprintf('PIT-%d-%02d', $sIdx + 1, $p),
                    'name' => sprintf('%s Blok %02d', $sc['code'], $p),
                    'target_production_ton' => 5000 + ($p * 250),
                    'actual_production_ton' => 4800 + ($p * 240),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DB::table('min_pits')->insert($pitRows);

        // 3. 600 Unit Alat Berat (120 per site; seluruh 8 EquipmentStatus muncul)
        $eqStatuses = [
            EquipmentStatus::AVAILABLE->value,
            EquipmentStatus::ASSIGNED->value,
            EquipmentStatus::OPERATING->value,
            EquipmentStatus::STANDBY->value,
            EquipmentStatus::BREAKDOWN->value,
            EquipmentStatus::UNDER_MAINTENANCE->value,
            EquipmentStatus::RETURNED_TO_SERVICE->value,
            EquipmentStatus::RETIRED->value,
        ];

        $equipmentRows = [];
        $truckIdsBySite = [];
        $shovelIdsBySite = [];

        $eqCounter = 1;
        foreach ($siteIds as $siteCode => $siteId) {
            $truckIdsBySite[$siteId] = [];
            $shovelIdsBySite[$siteId] = [];
            for ($i = 1; $i <= 120; $i++) {
                $eqId = (string) Str::uuid();
                $isShovel = ($i <= 20);
                $type = $isShovel ? 'EXCAVATOR' : 'HAUL_TRUCK';
                // Pastikan mayoritas truk berstatus available/operating agar kapasitas dispatch terpenuhi, namun semua 8 status muncul
                $status = ($i <= 104)
                    ? $eqStatuses[$i % 4]
                    : $eqStatuses[4 + ($i % 4)];

                if ($isShovel) {
                    $shovelIdsBySite[$siteId][] = $eqId;
                } else {
                    $truckIdsBySite[$siteId][] = $eqId;
                }

                $equipmentRows[] = [
                    'id' => $eqId,
                    'site_id' => $siteId,
                    'equipment_code' => sprintf('EQ-93-%04d', $eqCounter++),
                    'type' => $type,
                    'model' => $isShovel ? 'Komatsu PC2000-8' : 'Caterpillar 777D',
                    'capacity_ton' => $isShovel ? 12.0 : 95.0,
                    'capacity_kg' => $isShovel ? 12000 : 95000,
                    'status' => $status,
                    'engine_hours' => 1200 + (($i * 37) % 4500),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($equipmentRows, 200) as $chunk) {
            DB::table('min_equipment')->insert($chunk);
        }

        // 4. Rencana Tambang (seluruh 8 MinePlanStatus muncul) & Riwayat 180 Hari Shift + Siklus Angkut
        $planStatuses = [
            MinePlanStatus::DRAFT->value,
            MinePlanStatus::SUBMITTED->value,
            MinePlanStatus::REVIEWED->value,
            MinePlanStatus::APPROVED->value,
            MinePlanStatus::ACTIVE->value,
            MinePlanStatus::REVISED->value,
            MinePlanStatus::CLOSED->value,
            MinePlanStatus::CANCELLED->value,
        ];

        $primarySiteId = $siteIds['SITE-KALTIM-93'];
        $primaryPitId = $pitIdsBySite[$primarySiteId][0];
        $primaryPlanId = (string) Str::uuid();

        $planRows = [];
        foreach ($planStatuses as $idx => $pStatus) {
            $pid = ($idx === 4) ? $primaryPlanId : (string) Str::uuid();
            $planRows[] = [
                'id' => $pid,
                'site_id' => $primarySiteId,
                'plan_code' => sprintf('PLAN-93-SM-%02d-%s', $idx + 1, strtoupper($pStatus)),
                'period_month' => sprintf('2026-%02d', 4 + $idx),
                'commodity' => 'COAL',
                'target_cut_kg' => 120000000,
                'target_fill_kg' => 20000000,
                'target_ore_kg' => 100003000,
                'target_grade_ppm' => 5200,
                'status' => $pStatus,
                'version' => 1,
                'approved_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('min_mine_plans')->insert($planRows);

        $cycleStatuses = [
            HaulCycleStatus::QUEUED->value,
            HaulCycleStatus::ASSIGNED->value,
            HaulCycleStatus::TRAVELLING_EMPTY->value,
            HaulCycleStatus::LOADING->value,
            HaulCycleStatus::HAULING->value,
            HaulCycleStatus::DUMPING->value,
            HaulCycleStatus::COMPLETED->value,
            HaulCycleStatus::ABORTED->value,
        ];

        $startHistory = CarbonImmutable::parse('2026-05-10');
        $shiftRows = [];
        $cycleRows = [];
        $trucks = $truckIdsBySite[$primarySiteId];
        $shovels = $shovelIdsBySite[$primarySiteId];

        for ($day = 0; $day < 180; $day++) {
            $dStr = $startHistory->addDays($day)->toDateString();
            $isRainSeason = ($day >= 120 && $day < 150);
            $rainHours = $isRainSeason ? 3 : 0;

            foreach (['SHIFT_A', 'SHIFT_B'] as $sIdx => $shiftCode) {
                $targetKg = 500000;
                $actualKg = $isRainSeason ? 380000 : 495000;

                $shiftRows[] = [
                    'id' => (string) Str::uuid(),
                    'mine_plan_id' => $primaryPlanId,
                    'pit_id' => $primaryPitId,
                    'shift_date' => $dStr,
                    'shift_code' => $shiftCode,
                    'target_tonnage_kg' => $targetKg,
                    'actual_tonnage_kg' => $actualKg,
                    'effective_hours_nominal' => 10,
                    'effective_hours_adjusted' => 10 - $rainHours,
                    'rain_downtime_hours' => $rainHours,
                    'maintenance_downtime_hours' => 0,
                    'variance_kg' => $actualKg - $targetKg,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Buat siklus angkut ringkas per shift; pastikan 8 status muncul pada hari terakhir, sisanya completed/aborted
                $cStatus = ($day === 179 && $sIdx === 0)
                    ? $cycleStatuses[$day % 8]
                    : HaulCycleStatus::COMPLETED->value;

                $cycleRows[] = [
                    'id' => (string) Str::uuid(),
                    'site_id' => $primarySiteId,
                    'pit_id' => $primaryPitId,
                    'truck_id' => $trucks[($day + $sIdx) % 30],
                    'shovel_id' => $shovels[($day + $sIdx) % 5],
                    'shift_date' => $dStr,
                    'shift_code' => $shiftCode,
                    'cycle_seq' => $sIdx + 1,
                    'status' => $cStatus,
                    'target_payload_kg' => 95000,
                    'actual_payload_kg' => 94000,
                    'distance_meters' => 5000,
                    'fuel_consumed_ml' => 75000,
                    'ton_km_scaled' => 470,
                    'is_override' => ($day % 25 === 0),
                    'override_reason' => ($day % 25 === 0) ? 'CASE-93-Override pengalihan shovel maintenance' : null,
                    'override_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Pastikan seluruh 8 status HaulCycleStatus muncul secara eksplisit pada shift terakhir
        foreach ($cycleStatuses as $cIdx => $cs) {
            $cycleRows[] = [
                'id' => (string) Str::uuid(),
                'site_id' => $primarySiteId,
                'pit_id' => $primaryPitId,
                'truck_id' => $trucks[$cIdx],
                'shovel_id' => $shovels[$cIdx % 5],
                'shift_date' => '2026-11-07',
                'shift_code' => 'SHIFT_A',
                'cycle_seq' => 10 + $cIdx,
                'status' => $cs,
                'target_payload_kg' => 95000,
                'actual_payload_kg' => $cs === HaulCycleStatus::COMPLETED->value ? 95000 : 0,
                'distance_meters' => 5000,
                'fuel_consumed_ml' => 72000,
                'ton_km_scaled' => 475,
                'is_override' => false,
                'override_reason' => null,
                'override_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($shiftRows, 200) as $chunk) {
            DB::table('min_mine_plan_shifts')->insert($chunk);
        }
        foreach (array_chunk($cycleRows, 200) as $chunk) {
            DB::table('min_haul_cycles')->insert($chunk);
        }

        // 5. 15 Kasus Unik Bernama (CASE-93-01 s/d CASE-93-15) & Seluruh 8 Status FuelAnomalyCaseStatus
        // Gunakan FuelAnomalyHoldSaga untuk kasus PAYMENT_HELD (CASE-93-06) agar buku besar seimbang!
        $fuelSaga = app(FuelAnomalyHoldSaga::class);
        $fuelSaga->execute([
            'site_id' => $primarySiteId,
            'equipment_id' => $trucks[0],
            'contractor_party_id' => 'CONT-MIN-01',
            'shift_date' => '2026-11-01',
            'shift_code' => 'SHIFT_A',
            'historical_ton_km_thousands' => [10, 20, 30, 40, 50],
            'historical_fuel_liters' => [230, 410, 620, 800, 1010],
            'actual_ton_km_thousands' => 35,
            'actual_fuel_liters' => 860,
            'fuel_rate_per_liter_idr' => 15000,
        ]);

        // Buat 14 kasus bernama sisanya (mencakup status non-PAYMENT_HELD agar saldo ledger hold tetap sama persis dengan kasus PAYMENT_HELD)
        $nonHeldStatuses = [
            FuelAnomalyCaseStatus::DETECTED->value,
            FuelAnomalyCaseStatus::VERIFYING->value,
            FuelAnomalyCaseStatus::CONFIRMED->value,
            FuelAnomalyCaseStatus::DISPUTED->value,
            FuelAnomalyCaseStatus::RESOLVED_DEDUCTED->value,
            FuelAnomalyCaseStatus::RESOLVED_RELEASED->value,
            FuelAnomalyCaseStatus::WRITTEN_OFF->value,
        ];

        for ($c = 1; $c <= 15; $c++) {
            if ($c === 6) {
                continue; // CASE-93-06 sudah dibuat via FuelAnomalyHoldSaga (PAYMENT_HELD)
            }
            $st = $nonHeldStatuses[($c - 1) % count($nonHeldStatuses)];
            MiningFuelAnomalyCase::create([
                'case_number' => sprintf('CASE-93-%02d', $c),
                'site_id' => $primarySiteId,
                'contractor_party_ref' => sprintf('CONT-MIN-%02d', ($c % 30) + 1),
                'equipment_id' => $trucks[$c % 20],
                'shift_date' => CarbonImmutable::parse('2026-10-01')->addDays($c)->toDateString(),
                'shift_code' => ($c % 2 === 0) ? 'SHIFT_A' : 'SHIFT_B',
                'actual_fuel_ml' => 850000,
                'baseline_fuel_ml' => 700000,
                'excess_ml' => 150000,
                'excess_bps' => 2142,
                'held_amount_idr' => 2250000,
                'status' => $st,
                'dispute_reason' => $st === FuelAnomalyCaseStatus::DISPUTED->value ? "Sanggahan resmi kontraktor pada CASE-93-{$c}" : null,
            ]);
        }

        // 6. Bukukan 1 penutupan shift contoh via SettleShiftHaulingBillingAction
        app(SettleShiftHaulingBillingAction::class)->execute([
            'site_id' => $primarySiteId,
            'shift_date' => '2026-11-06',
            'shift_code' => 'SHIFT_A',
            'contractor_party_id' => 'CONT-MIN-01',
            'rate_per_ton_km_minor' => 1500,
            'idempotency_key' => 'min:seed-93:shift-close:2026-11-06:SHIFT_A',
        ]);
    }
}
