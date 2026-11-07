<?php

declare(strict_types=1);

namespace Tests\Acceptance\MiningOps;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Banking\Contracts\LedgerAccount;
use Modules\Mining\Application\Actions\DispatchHaulTruckAction;
use Modules\Mining\Application\Actions\PlanMineBlockAction;
use Modules\Mining\Application\Actions\SettleShiftHaulingBillingAction;
use Modules\Mining\database\seeders\MiningRbacSeeder;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningOpsE2EAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_e2e_mine_planning_dispatch_and_shift_close_ledger_posting(): void
    {
        $this->seed();
        (new MiningRbacSeeder)->run();

        $planner = User::factory()->create();
        $planner->assignRole('mine_planner');

        $dispatcher = User::factory()->create();
        $dispatcher->assignRole('dispatcher_mine');

        $manager = User::factory()->create();
        $manager->assignRole('mine_manager');

        $site = MiningSite::create([
            'site_code' => 'SITE-KUTAI-01',
            'name' => 'Kutai Coal Site',
            'commodity' => 'COAL',
            'location' => 'Kalimantan Timur',
            'timezone' => 'Asia/Makassar',
            'status' => 'ACTIVE',
        ]);

        $pit = MiningPit::create([
            'site_id' => $site->id,
            'pit_code' => 'PIT-MAHAKAM-A',
            'name' => 'Mahakam Pit A',
            'target_production_ton' => 100000,
            'actual_production_ton' => 0,
        ]);

        $shovel = MiningEquipment::create([
            'site_id' => $site->id,
            'equipment_code' => 'SHV-001',
            'type' => 'EXCAVATOR',
            'model' => 'Komatsu PC2000',
            'capacity_ton' => 150,
            'status' => 'available',
            'engine_hours' => 1200,
        ]);

        $truck = MiningEquipment::create([
            'site_id' => $site->id,
            'equipment_code' => 'TRK-101',
            'type' => 'HAUL_TRUCK',
            'model' => 'CAT 777D',
            'capacity_ton' => 100,
            'status' => 'available',
            'engine_hours' => 2400,
        ]);

        // 1. Create Mine Plan & Time-phased shifts
        $planAction = app(PlanMineBlockAction::class);
        $plan = $planAction->execute([
            'site_id' => $site->id,
            'pit_id' => $pit->id,
            'period_month' => '2026-11',
            'commodity' => 'COAL',
            'target_tonnage_kg' => 100003000, // 100.003 ton
            'target_grade_ppm' => 5000,
            'user_id' => $planner->id,
        ]);

        $this->assertNotNull($plan);
        $this->assertEquals('approved', $plan->status->value);
        $this->assertGreaterThan(0, $plan->shifts()->count());

        // 2. Dispatch truck using NeedTimeTruckDispatcher M2
        $dispatchAction = app(DispatchHaulTruckAction::class);
        $assignment = $dispatchAction->execute([
            'site_id' => $site->id,
            'pit_id' => $pit->id,
            'truck_id' => $truck->id,
            'shift_date' => '2026-11-01',
            'shift_code' => 'SHIFT_A',
            'target_payload_kg' => 95000,
            'dispatcher_id' => $dispatcher->id,
        ]);

        $this->assertNotNull($assignment);
        $this->assertEquals('assigned', $assignment->status->value);
        $this->assertEquals($shovel->id, $assignment->shovel_id);

        // 3. Complete haul cycle with payload & distance
        $assignment->update([
            'status' => 'completed',
            'actual_payload_kg' => 94000,
            'distance_meters' => 5000,
            'fuel_consumed_ml' => 85000,
        ]);

        // 4. Shift close & Contractor billing ledger posting
        $closeAction = app(SettleShiftHaulingBillingAction::class);
        $idempotencyKey = 'min:shift-close:SITE-KUTAI-01:2026-11-01:SHIFT_A';

        $contractorPartyId = 'CONT-BORNEO-01';
        $ratePerTonKmMinor = 1500; // Rp 1.500 per ton-km

        $result1 = $closeAction->execute([
            'site_id' => $site->id,
            'shift_date' => '2026-11-01',
            'shift_code' => 'SHIFT_A',
            'contractor_party_id' => $contractorPartyId,
            'rate_per_ton_km_minor' => $ratePerTonKmMinor,
            'idempotency_key' => $idempotencyKey,
            'user_id' => $manager->id,
        ]);

        $this->assertNotNull($result1);
        $this->assertGreaterThan(0, $result1['amount_minor']);

        // Check Ledger Accounts
        $expenseAccount = LedgerAccount::where('code', 'expense:min:hauling_contractor:IDR')->first();
        $payableAccount = LedgerAccount::where('code', "ap:min:contractor:{$contractorPartyId}:IDR")->first();

        $this->assertNotNull($expenseAccount);
        $this->assertNotNull($payableAccount);

        // Idempotency check: rerun with same key
        $result2 = $closeAction->execute([
            'site_id' => $site->id,
            'shift_date' => '2026-11-01',
            'shift_code' => 'SHIFT_A',
            'contractor_party_id' => $contractorPartyId,
            'rate_per_ton_km_minor' => $ratePerTonKmMinor,
            'idempotency_key' => $idempotencyKey,
            'user_id' => $manager->id,
        ]);

        $this->assertEquals($result1['amount_minor'], $result2['amount_minor']);
        $this->assertEquals($result1['journal_id'], $result2['journal_id']);
    }
}
