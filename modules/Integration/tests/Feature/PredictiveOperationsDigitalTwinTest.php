<?php

declare(strict_types=1);

namespace Modules\Integration\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Integration\Application\Services\PredictiveOperationsDigitalTwinService;
use Tests\TestCase;

class PredictiveOperationsDigitalTwinTest extends TestCase
{
    use RefreshDatabase;

    protected PredictiveOperationsDigitalTwinService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PredictiveOperationsDigitalTwinService::class);
    }

    public function test_twin_fidelity_monitoring_and_drift_alert(): void
    {
        // 1. High fidelity model (94.5% >= 90%) passes with no drift alert (307.3 & 307.4)
        $healthyModel = $this->service->monitorTwinFidelity('TWIN-HVAC-01', 'HVAC_ENERGY_PLANT', 94.5, 90.0);
        $this->assertFalse((bool) $healthyModel->drift_alert_triggered);

        // 2. Low fidelity model (82.0% < 90%) triggers drift alert (307.3 & 307.4)
        $driftModel = $this->service->monitorTwinFidelity('TWIN-ROBOT-02', 'ROBOTIC_ASSEMBLY', 82.0, 90.0);
        $this->assertTrue((bool) $driftModel->drift_alert_triggered);
    }

    public function test_twin_control_action_fidelity_gating_and_damping(): void
    {
        // Setup models
        $this->service->monitorTwinFidelity('TWIN-DATACENTER-HIGH', 'DATA_CENTER_COOLING', 95.0, 90.0);
        $this->service->monitorTwinFidelity('TWIN-DATACENTER-LOW', 'DATA_CENTER_COOLING', 78.0, 90.0);

        // 1. High fidelity model auto-executes control action (307.1 & 307.4)
        $applied = $this->service->proposeTwinControlAction('ACT-SETPOINT-01', 'TWIN-DATACENTER-HIGH', 'ADJUST_HVAC_SETPOINT', 15.0);
        $this->assertTrue((bool) $applied->auto_execution_applied);
        $this->assertEquals('APPLIED', $applied->execution_status);

        // 2. Edge case 307.5: Low fidelity model recommendation is downgraded to ADVISORY_ONLY (not auto-applied)
        $advisory = $this->service->proposeTwinControlAction('ACT-SETPOINT-02', 'TWIN-DATACENTER-LOW', 'ADJUST_HVAC_SETPOINT', 10.0);
        $this->assertFalse((bool) $advisory->auto_execution_applied);
        $this->assertEquals('ADVISORY_ONLY', $advisory->execution_status);

        // 3. Excessive damping factor (> 20%) rejected to prevent oscillatory instability (307.6 Risk)
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Feedback loop risk breach: Damping change factor cannot exceed 20%');
        $this->service->proposeTwinControlAction('ACT-UNSTABLE', 'TWIN-DATACENTER-HIGH', 'SETPOINT', 35.0);
    }

    public function test_quality_audit_clean_and_discrepancy(): void
    {
        // Healthy setup
        $this->service->monitorTwinFidelity('TWIN-AUD', 'SYS', 92.0, 90.0);
        $this->service->proposeTwinControlAction('ACT-AUD', 'TWIN-AUD', 'ACTION', 10.0);

        $audit = $this->service->audit();
        $this->assertEquals('HEALTHY', $audit['status']);
        $this->assertEquals(0, $audit['discrepancy_count']);

        // Inject discrepancy: low fidelity with auto execution
        DB::table('digital_twin_control_actions')->insert([
            'action_code' => 'ACT-IMPROPER-EXEC',
            'model_code' => 'TWIN-AUD',
            'recommended_action' => 'ACT',
            'model_fidelity_at_recommendation' => 60.0,
            'auto_execution_applied' => true, // Discrepancy!
            'execution_status' => 'APPLIED',
            'damping_factor_pct' => 10.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditDiscrepant = $this->service->audit();
        $this->assertEquals('DISCREPANCY_DETECTED', $auditDiscrepant['status']);
        $this->assertGreaterThan(0, $auditDiscrepant['discrepancy_count']);
    }
}
