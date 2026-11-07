<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningOps;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\Mining\database\seeders\MiningRbacSeeder;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningOpsAuditAndCorruptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mining_audit_passes_on_clean_data_and_fails_on_corruption(): void
    {
        $this->seed();
        (new MiningRbacSeeder)->run();

        $site = MiningSite::create([
            'site_code' => 'SITE-KUTAI-AUDIT',
            'name' => 'Kutai Audit Site',
            'commodity' => 'COAL',
            'location' => 'Kalimantan Timur',
            'timezone' => 'Asia/Makassar',
            'status' => 'ACTIVE',
        ]);

        $pit = MiningPit::create([
            'site_id' => $site->id,
            'pit_code' => 'PIT-AUDIT-01',
            'name' => 'Pit Audit 01',
            'target_production_ton' => 50000,
            'actual_production_ton' => 0,
        ]);

        $shovel = MiningEquipment::create([
            'site_id' => $site->id,
            'equipment_code' => 'SHV-AUDIT-01',
            'type' => 'EXCAVATOR',
            'model' => 'Komatsu PC2000',
            'capacity_ton' => 150,
            'status' => 'available',
            'engine_hours' => 1000,
        ]);

        $truck = MiningEquipment::create([
            'site_id' => $site->id,
            'equipment_code' => 'TRK-AUDIT-01',
            'type' => 'HAUL_TRUCK',
            'model' => 'CAT 777D',
            'capacity_ton' => 100,
            'status' => 'available',
            'engine_hours' => 1000,
        ]);

        // Clean state
        $exitClean = Artisan::call('min:audit');
        $this->assertEquals(0, $exitClean, 'Audit must return 0 on clean dataset');

        // Corrupt dataset: Create a fuel anomaly case with held amount, but tamper the ledger balance to mismatch
        $case = MiningFuelAnomalyCase::create([
            'case_number' => 'FAC-CORRUPT-001',
            'site_id' => $site->id,
            'contractor_party_id' => 'CONT-CORRUPT',
            'equipment_id' => $truck->id,
            'shift_date' => '2026-11-03',
            'shift_code' => 'SHIFT_A',
            'actual_fuel_ml' => 900000,
            'baseline_fuel_ml' => 700000,
            'excess_ml' => 200000,
            'excess_bps' => 2857,
            'held_amount_idr' => 3000000, // 3 million IDR held
            'status' => 'payment_held',
        ]);

        // Run audit on corrupted state (held case exists with 3 million, but ledger hold balance is 0)
        $exitCorrupt = Artisan::call('min:audit');
        $this->assertEquals(1, $exitCorrupt, 'Audit must fail (exit 1) when fuel hold balance mismatches ledger');
    }
}
