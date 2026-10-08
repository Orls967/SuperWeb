<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Application\Services\AutonomousFieldFleetService;
use Tests\TestCase;

class AutonomousFieldFleetTest extends TestCase
{
    use RefreshDatabase;

    protected AutonomousFieldFleetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AutonomousFieldFleetService::class);
    }

    public function test_autonomous_unit_registration_and_geofence_emergency_stop(): void
    {
        // 1. Register autonomous haul truck (306.1 & 306.6)
        $unit = $this->service->registerFleetUnit('TRUCK-HAUL-01', 'HAUL_TRUCK', 'MINE_PIT_A', 25.0);
        $this->assertTrue((bool) $unit->geofence_cage_active);
        $this->assertFalse((bool) $unit->emergency_stopped);

        // 2. Geofence violation instantly emergency stops unit (306.4 & 306.6)
        $breached = $this->service->checkSafetyPerimeter('TRUCK-HAUL-01', true, false);
        $this->assertTrue((bool) $breached->emergency_stopped);
        $this->assertEquals('GEOFENCE_PERIMETER_BREACH', $breached->emergency_stop_reason);
    }

    public function test_pedestrian_hold_requires_mandatory_operator_intervention(): void
    {
        // 1. Unit entering dense pedestrian zone emergency stops (306.4 & 306.5 Edge Case)
        $this->service->registerFleetUnit('AGV-WH-02', 'AGV', 'WAREHOUSE_CORRIDOR_5', 10.0);
        $stopped = $this->service->checkSafetyPerimeter('AGV-WH-02', false, true);
        $this->assertTrue((bool) $stopped->emergency_stopped);
        $this->assertEquals('PEDESTRIAN_ZONE_SAFETY_HOLD', $stopped->emergency_stop_reason);

        // 2. ROC operator intervenes to inspect area and release unit (306.2, 306.4, 306.5)
        $intervention = $this->service->recordOperatorIntervention(
            interventionCode: 'INT-WH-AGV-001',
            unitCode: 'AGV-WH-02',
            operatorId: 'ROC_OPERATOR_YUSUF',
            interventionType: 'PEDESTRIAN_CONGESTION_HOLD',
            actionNotes: 'Audited visual feed; pedestrian cleared. Switched unit back to path with speed restricted.'
        );
        $this->assertTrue((bool) $intervention->safe_handover_verified);

        // Unit cleared and no longer emergency stopped
        $unitAfter = DB::table('autonomous_fleet_units')->where('unit_code', 'AGV-WH-02')->first();
        $this->assertFalse((bool) $unitAfter->emergency_stopped);
    }

    public function test_autonomous_fleet_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->registerFleetUnit('AMR-AUD', 'AMR', 'ZONE', 15.0);
        $this->service->recordOperatorIntervention('INT-AUD', 'AMR-AUD', 'OP1', 'FALLBACK', 'Notes');

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: unverified intervention
        DB::table('autonomous_fleet_interventions')->insert([
            'intervention_code' => 'INT-UNVERIFIED',
            'unit_code' => 'AMR-AUD',
            'remote_operator_id' => 'OP_DEFECT',
            'intervention_type' => 'FALLBACK',
            'operator_action_notes' => 'None',
            'safe_handover_verified' => false, // Discrepancy!
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
