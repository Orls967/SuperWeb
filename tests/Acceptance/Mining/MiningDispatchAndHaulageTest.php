<?php

declare(strict_types=1);

namespace Tests\Acceptance\Mining;

use Database\Seeders\LedgerAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Mining\Application\Actions\GenerateDispatchPlanAction;
use Modules\Mining\Domain\Algorithms\AssignmentCertificateVerifier;
use Modules\Mining\Domain\Models\MiningArea;
use Modules\Mining\Domain\Models\MiningDispatchAssignment;
use Modules\Mining\Domain\Models\MiningDispatchPlan;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Mining\Domain\Models\MiningShovelSlot;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Domain\Models\MiningWorkPermit;
use Modules\Shared\Domain\Algorithms\Assignment\HungarianSolver;
use Tests\TestCase;

class MiningDispatchAndHaulageTest extends TestCase
{
    use RefreshDatabase;

    protected MiningSite $site;

    protected MiningPit $pit;

    protected MiningArea $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LedgerAccountsSeeder::class);

        DB::table('users')->insertOrIgnore([
            ['id' => 99, 'name' => 'Dispatcher 99', 'email' => 'dispatcher99@test.com', 'password' => 'secret'],
        ]);

        $this->site = MiningSite::create([
            'id' => (string) Str::uuid(),
            'site_code' => 'SITE-SUL-01',
            'name' => 'Pomalaa Nickel Site',
            'commodity' => 'NICKEL',
            'timezone' => 'Asia/Makassar',
            'location' => 'Kolaka',
            'status' => 'ACTIVE',
        ]);

        $this->pit = MiningPit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_code' => 'PIT-NORTH-01',
            'name' => 'North Pit',
            'target_production_ton' => 50000,
            'status' => 'ACTIVE',
        ]);

        $this->area = MiningArea::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_id' => $this->pit->id,
            'area_code' => 'AREA-DIG-01',
            'name' => 'Digging Face 1',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_hungariansolver_menghasilkan_alokasi_penugasan_truk_ke_shovel_yang_optimal(): void
    {
        // Setup 2 Shovels with slots
        $shovel1 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'SHV-01',
            'type' => 'EXCAVATOR',
            'model' => 'Cat 6020B',
            'tare_weight_kg' => 200000,
            'capacity_kg' => 20000,
            'status' => 'AVAILABLE',
        ]);
        $slot1 = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel1->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV1-01',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 10,
        ]);

        $shovel2 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'SHV-02',
            'type' => 'EXCAVATOR',
            'model' => 'Hitachi EX2600',
            'tare_weight_kg' => 250000,
            'capacity_kg' => 25000,
            'status' => 'AVAILABLE',
        ]);
        $slot2 = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel2->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV2-01',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 10,
        ]);

        // Setup 2 Haul Trucks
        $truck1 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-01',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);
        $truck2 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-02',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);

        // Cost matrix (seconds):
        // Truck 1: Shovel 1 = 600s, Shovel 2 = 900s
        // Truck 2: Shovel 1 = 800s, Shovel 2 = 700s
        // Optimal: Truck 1 -> Shovel 1 (600s) + Truck 2 -> Shovel 2 (700s) = 1300s (better than 900+800=1700s)
        $costMatrix = [
            $truck1->id => [$slot1->id => 600, $slot2->id => 900],
            $truck2->id => [$slot1->id => 800, $slot2->id => 700],
        ];

        $action = app(GenerateDispatchPlanAction::class);
        $res = $action->handle(
            siteId: $this->site->id,
            shiftCode: 'SHIFT-MORNING',
            truckIds: [$truck1->id, $truck2->id],
            shovelSlotIds: [$slot1->id, $slot2->id],
            costMatrix: $costMatrix,
            idempotencyKey: 'min:disp:plan:001'
        );

        $this->assertSame(1300, $res->plan->total_cost_seconds);
        $this->assertCount(2, $res->assignments);
        $this->assertDatabaseHas('min_dispatch_assignments', [
            'dispatch_plan_id' => $res->plan->id,
            'truck_id' => $truck1->id,
            'shovel_slot_id' => $slot1->id,
            'estimated_cycle_seconds' => 600,
        ]);
        $this->assertDatabaseHas('min_dispatch_assignments', [
            'dispatch_plan_id' => $res->plan->id,
            'truck_id' => $truck2->id,
            'shovel_slot_id' => $slot2->id,
            'estimated_cycle_seconds' => 700,
        ]);
    }

    public function test_truk_berstatus_grounded_dikeluarkan_dari_kandidat_dispatch(): void
    {
        $truckGrounded = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-GND-01',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'GROUNDED',
        ]);

        $shovel = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'SHV-03',
            'type' => 'EXCAVATOR',
            'model' => 'Cat 6020B',
            'tare_weight_kg' => 200000,
            'capacity_kg' => 20000,
            'status' => 'AVAILABLE',
        ]);
        $slot = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV3-01',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/grounded|tidak layak/i');

        $action = app(GenerateDispatchPlanAction::class);
        $action->handle(
            siteId: $this->site->id,
            shiftCode: 'SHIFT-MORNING',
            truckIds: [$truckGrounded->id],
            shovelSlotIds: [$slot->id],
            costMatrix: [$truckGrounded->id => [$slot->id => 600]],
            idempotencyKey: 'min:disp:plan:002'
        );
    }

    public function test_operator_dengan_izin_kerja_kedaluwarsa_dikeluarkan(): void
    {
        $truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-EXP-01',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);
        $shovel = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'SHV-04',
            'type' => 'EXCAVATOR',
            'model' => 'Cat 6020B',
            'tare_weight_kg' => 200000,
            'capacity_kg' => 20000,
            'status' => 'AVAILABLE',
        ]);
        $slot = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV4-01',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);

        // Expired permit for operator assigned to truck
        MiningWorkPermit::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'permit_number' => 'PTW-EXP-01',
            'permit_type' => 'HAULAGE_OPERATION',
            'worker_party_id' => 'DVR-EXP',
            'valid_from' => now()->subDays(5),
            'valid_until' => now()->subDay(),
            'approval_status' => 'APPROVED',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/izin.*kedaluwarsa|permit/i');

        $action = app(GenerateDispatchPlanAction::class);
        $action->handle(
            siteId: $this->site->id,
            shiftCode: 'SHIFT-MORNING',
            truckIds: [$truck->id],
            shovelSlotIds: [$slot->id],
            costMatrix: [$truck->id => [$slot->id => 600]],
            idempotencyKey: 'min:disp:plan:003',
            driverMapping: [$truck->id => 'DVR-EXP']
        );
    }

    public function test_shovel_pada_area_dihentikan_dikeluarkan(): void
    {
        $stoppedArea = MiningArea::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'pit_id' => $this->pit->id,
            'area_code' => 'AREA-STOP-01',
            'name' => 'Stopped Sector',
            'status' => 'STOPPED',
            'stop_reason' => 'High wall instability',
            'stopped_at' => now(),
        ]);

        $shovel = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'SHV-STOP-01',
            'type' => 'EXCAVATOR',
            'model' => 'Cat 6020B',
            'tare_weight_kg' => 200000,
            'capacity_kg' => 20000,
            'status' => 'AVAILABLE',
        ]);
        $slot = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $stoppedArea->id,
            'slot_code' => 'SLOT-STOP-01',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);
        $truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-OK-01',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/area.*dihentikan|stopped/i');

        $action = app(GenerateDispatchPlanAction::class);
        $action->handle(
            siteId: $this->site->id,
            shiftCode: 'SHIFT-MORNING',
            truckIds: [$truck->id],
            shovelSlotIds: [$slot->id],
            costMatrix: [$truck->id => [$slot->id => 600]],
            idempotencyKey: 'min:disp:plan:004'
        );
    }

    public function test_sertifikat_dual_potensial_tersimpan_dan_valid_pr2(): void
    {
        $shovel = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'SHV-05',
            'type' => 'EXCAVATOR',
            'model' => 'Cat 6020B',
            'tare_weight_kg' => 200000,
            'capacity_kg' => 20000,
            'status' => 'AVAILABLE',
        ]);
        $slot = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV5-01',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);
        $truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-05',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);

        $action = app(GenerateDispatchPlanAction::class);
        $res = $action->handle(
            siteId: $this->site->id,
            shiftCode: 'SHIFT-MORNING',
            truckIds: [$truck->id],
            shovelSlotIds: [$slot->id],
            costMatrix: [$truck->id => [$slot->id => 500]],
            idempotencyKey: 'min:disp:plan:005'
        );

        $plan = MiningDispatchPlan::findOrFail($res->plan->id);
        $this->assertNotNull($plan->certificate_json);

        $verifier = app(AssignmentCertificateVerifier::class);
        $this->assertTrue($verifier->verify(
            costMatrix: [[500]],
            assignment: [0 => 0],
            totalCost: 500,
            certificate: json_decode($plan->certificate_json, true)
        ));
    }

    public function test_verifier_sertifikat_menolak_sertifikat_hasil_manipulasi(): void
    {
        $verifier = app(AssignmentCertificateVerifier::class);

        $corruptedCertificate = [
            'u' => [100],
            'v' => [200], // 100 + 200 = 300 != 500 (dual gap violation)
        ];

        $this->assertFalse($verifier->verify(
            costMatrix: [[500]],
            assignment: [0 => 0],
            totalCost: 500,
            certificate: $corruptedCertificate
        ));
    }

    public function test_override_dispatcher_tanpa_alasan_ditolak(): void
    {
        $shovel = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'SHV-06',
            'type' => 'EXCAVATOR',
            'model' => 'Cat 6020B',
            'tare_weight_kg' => 200000,
            'capacity_kg' => 20000,
            'status' => 'AVAILABLE',
        ]);
        $slot1 = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV6-01',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);
        $slot2 = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV6-02',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);
        $truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-06',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);

        $action = app(GenerateDispatchPlanAction::class);
        $res = $action->handle(
            siteId: $this->site->id,
            shiftCode: 'SHIFT-MORNING',
            truckIds: [$truck->id],
            shovelSlotIds: [$slot1->id, $slot2->id],
            costMatrix: [$truck->id => [$slot1->id => 500, $slot2->id => 800]],
            idempotencyKey: 'min:disp:plan:006'
        );

        $assignment = MiningDispatchAssignment::where('dispatch_plan_id', $res->plan->id)->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/alasan.*override|reason/i');

        $action->overrideAssignment(
            assignmentId: $assignment->id,
            newShovelSlotId: $slot2->id,
            userId: 99,
            reason: '' // Empty reason must be rejected!
        );
    }

    public function test_override_dispatcher_dengan_alasan_tersimpan_di_audit_trail(): void
    {
        $shovel = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'SHV-07',
            'type' => 'EXCAVATOR',
            'model' => 'Cat 6020B',
            'tare_weight_kg' => 200000,
            'capacity_kg' => 20000,
            'status' => 'AVAILABLE',
        ]);
        $slot1 = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV7-01',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);
        $slot2 = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV7-02',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);
        $truck = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-07',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);

        $action = app(GenerateDispatchPlanAction::class);
        $res = $action->handle(
            siteId: $this->site->id,
            shiftCode: 'SHIFT-MORNING',
            truckIds: [$truck->id],
            shovelSlotIds: [$slot1->id, $slot2->id],
            costMatrix: [$truck->id => [$slot1->id => 500, $slot2->id => 800]],
            idempotencyKey: 'min:disp:plan:007'
        );

        $assignment = MiningDispatchAssignment::where('dispatch_plan_id', $res->plan->id)->first();

        $action->overrideAssignment(
            assignmentId: $assignment->id,
            newShovelSlotId: $slot2->id,
            userId: 99,
            reason: 'Truk diarahkan ke Shovel 2 untuk pemerataan antrean pit selatan'
        );

        $updated = MiningDispatchAssignment::findOrFail($assignment->id);
        $this->assertTrue($updated->is_override);
        $this->assertSame($slot2->id, $updated->shovel_slot_id);
        $this->assertSame('Truk diarahkan ke Shovel 2 untuk pemerataan antrean pit selatan', $updated->override_reason);
    }

    public function test_determinisme_penugasan_pada_input_yang_sama_pr3(): void
    {
        $shovel = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'SHV-08',
            'type' => 'EXCAVATOR',
            'model' => 'Cat 6020B',
            'tare_weight_kg' => 200000,
            'capacity_kg' => 20000,
            'status' => 'AVAILABLE',
        ]);
        $slot1 = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV8-01',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);
        $slot2 = MiningShovelSlot::create([
            'id' => (string) Str::uuid(),
            'equipment_id' => $shovel->id,
            'area_id' => $this->area->id,
            'slot_code' => 'SLOT-SHV8-02',
            'hour_window' => 8,
            'capacity_trucks_per_hour' => 5,
        ]);
        $truck1 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-08A',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);
        $truck2 = MiningEquipment::create([
            'id' => (string) Str::uuid(),
            'site_id' => $this->site->id,
            'equipment_code' => 'TRK-08B',
            'type' => 'HAUL_TRUCK',
            'model' => 'HD785',
            'tare_weight_kg' => 35000,
            'capacity_kg' => 50000,
            'status' => 'AVAILABLE',
        ]);

        $costMatrix = [
            $truck1->id => [$slot1->id => 600, $slot2->id => 600],
            $truck2->id => [$slot1->id => 600, $slot2->id => 600],
        ];

        $action = app(GenerateDispatchPlanAction::class);

        $res1 = $action->handle(
            siteId: $this->site->id,
            shiftCode: 'SHIFT-MORNING',
            truckIds: [$truck1->id, $truck2->id],
            shovelSlotIds: [$slot1->id, $slot2->id],
            costMatrix: $costMatrix,
            idempotencyKey: 'min:disp:plan:008a'
        );

        $res2 = $action->handle(
            siteId: $this->site->id,
            shiftCode: 'SHIFT-MORNING',
            truckIds: [$truck1->id, $truck2->id],
            shovelSlotIds: [$slot1->id, $slot2->id],
            costMatrix: $costMatrix,
            idempotencyKey: 'min:disp:plan:008b'
        );

        // Ties must be broken deterministically by ID order
        $assignments1 = MiningDispatchAssignment::where('dispatch_plan_id', $res1->plan->id)->orderBy('truck_id')->pluck('shovel_slot_id')->all();
        $assignments2 = MiningDispatchAssignment::where('dispatch_plan_id', $res2->plan->id)->orderBy('truck_id')->pluck('shovel_slot_id')->all();

        $this->assertSame($assignments1, $assignments2);
    }

    public function test_benchmark_performa_hungarian_di_bawah_0_2_detik_pr6(): void
    {
        $n = 20;
        $matrix = [];
        for ($i = 0; $i < $n; $i++) {
            $row = [];
            for ($j = 0; $j < $n; $j++) {
                $row[$j] = 500 + (($i * 17 + $j * 23) % 400);
            }
            $matrix[$i] = $row;
        }

        $solver = app(HungarianSolver::class);

        $start = microtime(true);
        $result = $solver->solve($matrix);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.20, $elapsed);
        $this->assertCount($n, $result->assignment);
    }
}
