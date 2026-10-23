<?php

declare(strict_types=1);

namespace Modules\Mining\tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\CalculatePnbpRoyaltyFromVerifiedTonnageAction;
use Modules\Mining\Application\Actions\DispatchHaulTruckAndDetectFuelAnomalyAction;
use Modules\Mining\Application\Actions\EnforceBlastRadiusSafetyAndGeotechLockAction;
use Modules\Mining\Application\Actions\ExecuteSmelterMassBalanceRecoveryAction;
use Modules\Mining\Application\Actions\PlanMineBlockExtractionAndStrippingRatioAction;
use Modules\Mining\Application\Actions\PostReclamationGuaranteeBondAndAmortizeAction;
use Modules\Mining\Application\Actions\ReconcileOreGradeMineToPortVarianceAction;
use Modules\Mining\Application\Actions\RecordWeighbridgeHashChainTicketAction;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningSite;
use Tests\TestCase;

class MiningEnterpriseDeepeningAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $site;

    protected MiningPit $pit;

    protected MiningEquipment $truck;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'site_manager']);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'MINE-WEDA-01',
            'name' => 'Weda Bay Nickel Concession Block 4',
            'commodity' => 'NICKEL',
            'location' => 'Central Halmahera',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-KAPAS-01',
            'name' => 'Kapas Nickel Main Pit',
            'target_production_ton' => 120000,
            'actual_production_ton' => 0,
        ]);

        $this->truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'DT-CAT-777D-09',
            'type' => 'DUMP_TRUCK',
            'model' => 'CAT 777D',
            'capacity_ton' => 100.0,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_plan_mine_block_extraction_and_stripping_ratio_action(): void
    {
        $action = app(PlanMineBlockExtractionAndStrippingRatioAction::class);

        $result = $action->handle(
            $this->site->id,
            $this->pit->id,
            50000.0,
            150000.0,
            1.85
        );

        $this->assertEquals(3.0, $result['stripping_ratio']);
        $this->assertTrue($result['is_economically_viable']);
        $this->assertGreaterThan(0, $result['estimated_cost_idr']);
        $this->assertEquals(50000, $result['pit']->target_production_ton);
    }

    public function test_dispatch_haul_truck_without_fuel_anomaly(): void
    {
        $action = app(DispatchHaulTruckAndDetectFuelAnomalyAction::class);

        // distance = 10km, baseline SFC = 18.5L. actual = 18.5L
        $result = $action->handle(
            $this->site->id,
            $this->pit->id,
            $this->truck->id,
            'SHIFT-DAY-01',
            95.0,
            96.2,
            18.5,
            10.0
        );

        $this->assertFalse($result['fuel_anomaly_detected']);
        $this->assertFalse($result['contractor_payment_held']);
        $this->assertEquals(1.2, $result['payload_variance_ton']);
        $this->assertEquals('completed', $result['dispatch_run']->status);
    }

    public function test_dispatch_haul_truck_flags_fuel_anomaly_and_holds_payment(): void
    {
        $action = app(DispatchHaulTruckAndDetectFuelAnomalyAction::class);

        // distance = 10km, baseline SFC = 18.5L. actual = 35.0L (> 12% over-consumption)
        $result = $action->handle(
            $this->site->id,
            $this->pit->id,
            $this->truck->id,
            'SHIFT-NIGHT-02',
            95.0,
            94.0,
            35.0,
            10.0
        );

        $this->assertTrue($result['fuel_anomaly_detected']);
        $this->assertTrue($result['contractor_payment_held']);
    }

    public function test_record_weighbridge_hash_chain_ticket_action(): void
    {
        $action = app(RecordWeighbridgeHashChainTicketAction::class);

        $ticket1 = $action->handle(
            $this->site->id,
            'B 9102 WDA',
            55.0,
            15.0,
            1.82,
            'STOCKPILE-EXPORT-A'
        );

        $this->assertEquals(40.0, $ticket1['net_weight_ton']);
        $this->assertNotEmpty($ticket1['hash']);

        // Record second ticket; verify it links previous hash
        $ticket2 = $action->handle(
            $this->site->id,
            'B 9103 WDA',
            60.0,
            15.0,
            1.86,
            'STOCKPILE-EXPORT-A'
        );

        $this->assertEquals(45.0, $ticket2['net_weight_ton']);
        $this->assertEquals($ticket1['hash'], $ticket2['ticket']->previous_hash);
    }

    public function test_reconcile_ore_grade_mine_to_port_variance_action(): void
    {
        $action = app(ReconcileOreGradeMineToPortVarianceAction::class);

        // Commercially acceptable variance (<= 0.10% Ni)
        $resOk = $action->handle('LOT-HAL-2026-001', 1.84, 1.81, 10000.0);
        $this->assertFalse($resOk['is_dispute_flagged']);
        $this->assertEquals(0.03, $resOk['grade_variance_pct']);

        // Dispute flagged variance (> 0.10% Ni)
        $resDispute = $action->handle('LOT-HAL-2026-002', 1.95, 1.72, 10000.0);
        $this->assertTrue($resDispute['is_dispute_flagged']);
        $this->assertEquals(0.23, $resDispute['grade_variance_pct']);
    }

    public function test_execute_smelter_mass_balance_recovery_action(): void
    {
        $action = app(ExecuteSmelterMassBalanceRecoveryAction::class);

        // 1000t ore * 1.80% Ni = 18.0 contained metal. 17.0t output metal => 94.44% recovery
        $result = $action->handle(
            $this->site->id,
            'RUN-RKEF-091',
            'FERRONICKEL_NPI',
            1000.0,
            1.80,
            17.0,
            880.0,
            150000000
        );

        $this->assertEquals(94.44, $result['recovery_rate_pct']);
        $this->assertTrue($result['is_mass_balance_conserved']);
        $this->assertEquals(150000000, $result['byproduct_revenue_idr']);
        $this->assertEquals('COMPLETED', $result['smelter_run']->status);
    }

    public function test_calculate_pnbp_royalty_from_verified_tonnage_action(): void
    {
        $action = app(CalculatePnbpRoyaltyFromVerifiedTonnageAction::class);

        $result = $action->handle(
            $this->site->id,
            '2026-10',
            25000.0,
            1200000,
            10.0
        );

        // 25,000 * 1,200,000 = 30,000,000,000 gross * 10% = 3,000,000,000
        $this->assertEquals(3000000000, $result['royalty_due_idr']);
        $this->assertEquals('accrued', $result['calculation']->status);
    }

    public function test_post_reclamation_guarantee_bond_and_amortize_action(): void
    {
        $action = app(PostReclamationGuaranteeBondAndAmortizeAction::class);

        // Case 1: Low NDVI (< 0.650)
        $resLow = $action->handle($this->site->id, 'REC-2026-A', 20.0, 500000000, 0.450);
        $this->assertFalse($resLow['is_bond_release_eligible']);
        $this->assertEquals(0, $resLow['provision_released_idr']);
        $this->assertEquals('VERIFIED', $resLow['status']);

        // Case 2: High NDVI (>= 0.650)
        $resHigh = $action->handle($this->site->id, 'REC-2026-B', 20.0, 500000000, 0.710);
        $this->assertTrue($resHigh['is_bond_release_eligible']);
        $this->assertEquals(400000000, $resHigh['provision_released_idr']); // 80% of 500,000,000
        $this->assertEquals('RELEASED', $resHigh['status']);
    }

    public function test_enforce_blast_radius_safety_and_geotech_lock_action(): void
    {
        $action = app(EnforceBlastRadiusSafetyAndGeotechLockAction::class);

        // Case 1: Personnel in radius -> hard block
        $blocked = $action->handle($this->pit->id, 'BLAST-LOCK-01', -2.53, 121.34, 40, 2000.0, 2);
        $this->assertFalse($blocked['is_clear_for_detonation']);
        $this->assertEquals('BLOCKED', $blocked['status']);

        // Case 2: Zone clear -> scheduled
        $cleared = $action->handle($this->pit->id, 'BLAST-LOCK-02', -2.53, 121.34, 40, 2000.0, 0);
        $this->assertTrue($cleared['is_clear_for_detonation']);
        $this->assertEquals('SCHEDULED', $cleared['status']);
    }

    public function test_mining_operations_http_routes_workflow(): void
    {
        // 1. Block planning
        $this->actingAs($this->user)->get(route('mining.operations.block-planning'))->assertOk();
        $this->actingAs($this->user)->post(route('mining.operations.block-planning.plan'), [
            'site_id' => $this->site->id,
            'pit_id' => $this->pit->id,
            'ore_tonnage' => 30000,
            'waste_overburden_bcm' => 90000,
            'target_grade_ni_pct' => 1.82,
        ])->assertRedirect(route('mining.operations.block-planning'));

        // 2. Haul dispatch
        $this->actingAs($this->user)->get(route('mining.operations.haul-dispatch'))->assertOk();
        $this->actingAs($this->user)->post(route('mining.operations.haul-dispatch.truck'), [
            'site_id' => $this->site->id,
            'pit_id' => $this->pit->id,
            'equipment_id' => $this->truck->id,
            'shift_code' => 'DS-HTTP-01',
            'target_payload_ton' => 95.0,
            'actual_payload_ton' => 95.5,
            'fuel_consumed_liter' => 18.5,
            'distance_km' => 10.0,
        ])->assertRedirect(route('mining.operations.haul-dispatch'));

        // 3. Weighbridge
        $this->actingAs($this->user)->get(route('mining.operations.weighbridge'))->assertOk();
        $this->actingAs($this->user)->post(route('mining.operations.weighbridge.ticket'), [
            'site_id' => $this->site->id,
            'truck_plate_number' => 'B 9999 WDA',
            'gross_weight_ton' => 60.0,
            'tare_weight_ton' => 15.0,
            'nickel_grade_percentage' => 1.85,
            'destination_stockpile' => 'STOCKPILE-1',
        ])->assertRedirect(route('mining.operations.weighbridge'));

        // 4. Ore reconciliation
        $this->actingAs($this->user)->get(route('mining.operations.grade-reconciliation'))->assertOk();
        $this->actingAs($this->user)->post(route('mining.operations.grade-reconciliation.reconcile'), [
            'lot_code' => 'LOT-TEST-99',
            'mine_grade_ni_pct' => 1.85,
            'port_grade_ni_pct' => 1.82,
            'lot_tonnage' => 5000.0,
        ])->assertRedirect(route('mining.operations.grade-reconciliation'));

        // 5. Smelter runs
        $this->actingAs($this->user)->get(route('mining.operations.smelter'))->assertOk();
        $this->actingAs($this->user)->post(route('mining.operations.smelter.run'), [
            'site_id' => $this->site->id,
            'run_code' => 'RUN-TEST-01',
            'output_commodity' => 'NPI',
            'input_ore_tonnage' => 1000.0,
            'input_grade_pct' => 1.8,
            'output_metal_tonnage' => 16.5,
            'byproduct_slag_tonnage' => 850.0,
            'byproduct_revenue_idr' => 120000000,
        ])->assertRedirect(route('mining.operations.smelter'));

        // 6. PNBP Royalty
        $this->actingAs($this->user)->get(route('mining.operations.pnbp-royalty'))->assertOk();
        $this->actingAs($this->user)->post(route('mining.operations.pnbp-royalty.calculate'), [
            'site_id' => $this->site->id,
            'period' => '2026-11',
            'total_production_ton' => 20000.0,
            'commodity_benchmark_price_idr' => 1200000,
            'royalty_rate_percentage' => 10.0,
        ])->assertRedirect(route('mining.operations.pnbp-royalty'));

        // 7. Reclamation provision bond
        $this->actingAs($this->user)->get(route('mining.operations.reclamation'))->assertOk();
        $this->actingAs($this->user)->post(route('mining.operations.reclamation.bond'), [
            'site_id' => $this->site->id,
            'plan_code' => 'REC-HTTP-01',
            'target_hectares' => 15.0,
            'provision_accrued_idr' => 300000000,
            'verified_growth_ndvi' => 0.680,
        ])->assertRedirect(route('mining.operations.reclamation'));

        // 8. Blast safety
        $this->actingAs($this->user)->get(route('mining.operations.blast-safety'))->assertOk();
        $this->actingAs($this->user)->post(route('mining.operations.blast-safety.lock'), [
            'pit_id' => $this->pit->id,
            'blast_code' => 'BLAST-HTTP-01',
            'blast_coord_lat' => -2.53,
            'blast_coord_lng' => 121.34,
            'holes_count' => 30,
            'explosives_kg' => 1500.0,
            'active_personnel_in_radius' => 0,
        ])->assertRedirect(route('mining.operations.blast-safety'));
    }
}
