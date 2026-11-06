<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningOps;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mining\database\seeders\MiningRbacSeeder;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningFuelAnomalyCase;
use Modules\Mining\Domain\Models\MiningHaulCycle;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningOpsContractorPortalIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_contractor_portal_strictly_prevents_idor_access(): void
    {
        $this->seed();
        (new MiningRbacSeeder)->run();

        $contractorUserA = User::factory()->create(['name' => 'PT Borneo Trans']);
        $contractorUserA->assignRole('contractor');

        $contractorUserB = User::factory()->create(['name' => 'PT Mahakam Logistik']);
        $contractorUserB->assignRole('contractor');

        $site = MiningSite::create([
            'site_code' => 'SITE-KUTAI-02',
            'name' => 'Kutai Site 2',
            'commodity' => 'COAL',
            'location' => 'Kalimantan Timur',
            'timezone' => 'Asia/Makassar',
            'status' => 'ACTIVE',
        ]);

        $pit = MiningPit::create([
            'site_id' => $site->id,
            'pit_code' => 'PIT-KUTAI-C',
            'name' => 'Kutai Pit C',
            'target_production_ton' => 30000,
            'actual_production_ton' => 0,
        ]);

        $truckA = MiningEquipment::create([
            'site_id' => $site->id,
            'equipment_code' => 'TRK-A01',
            'type' => 'HAUL_TRUCK',
            'model' => 'CAT 777D',
            'capacity_ton' => 100,
            'status' => 'available',
            'engine_hours' => 1000,
        ]);

        $shovel = MiningEquipment::create([
            'site_id' => $site->id,
            'equipment_code' => 'SHV-A01',
            'type' => 'EXCAVATOR',
            'model' => 'Komatsu PC2000',
            'capacity_ton' => 150,
            'status' => 'available',
            'engine_hours' => 1000,
        ]);

        $caseB = MiningFuelAnomalyCase::create([
            'case_number' => 'FAC-202611-0002',
            'site_id' => $site->id,
            'contractor_party_id' => (string) $contractorUserB->id,
            'equipment_id' => $truckA->id,
            'shift_date' => '2026-11-02',
            'shift_code' => 'SHIFT_A',
            'actual_fuel_ml' => 860000,
            'baseline_fuel_ml' => 711500,
            'excess_ml' => 148500,
            'excess_bps' => 2087,
            'held_amount_idr' => 2227500,
            'status' => 'payment_held',
        ]);

        // Contractor A accessing their own portal
        $responseA = $this->actingAs($contractorUserA)->get('/contractor/mining');
        $this->assertEquals(200, $responseA->getStatusCode());

        // Contractor A attempting to access/dispute Contractor B's fuel anomaly case
        $responseIdor = $this->actingAs($contractorUserA)->postJson("/contractor/mining/dispute", [
            'case_id' => $caseB->id,
            'reason' => 'Unauthorized dispute attempt by different contractor',
        ]);

        $this->assertEquals(403, $responseIdor->getStatusCode());
    }
}
