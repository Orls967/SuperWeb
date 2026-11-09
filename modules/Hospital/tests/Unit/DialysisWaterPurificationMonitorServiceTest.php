<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\DialysisWaterPurificationMonitorService;
use PHPUnit\Framework\TestCase;

class DialysisWaterPurificationMonitorServiceTest extends TestCase
{
    private DialysisWaterPurificationMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DialysisWaterPurificationMonitorService();
    }

    public function test_optimal_water_quality_passes_all_checks(): void
    {
        $result = $this->service->evaluateDialysisWaterQuality(
            'RO-LOOP-MAIN',
            5.5,   // conductivity < 10
            12.0,  // CFU < 50
            0.04,  // endotoxin < 0.125
            0.01   // chlorine < 0.10
        );

        $this->assertSame(DialysisWaterPurificationMonitorService::STATUS_OPTIMAL, $result['status']);
        $this->assertTrue($result['is_safe_for_patient_treatment']);
        $this->assertEmpty($result['breached_parameters']);
        $this->assertFalse($result['disinfection_protocol_required']);
    }

    public function test_chlorine_breach_immediately_halts_dialysis(): void
    {
        $result = $this->service->evaluateDialysisWaterQuality(
            'RO-LOOP-MAIN',
            6.0,
            10.0,
            0.02,
            0.15 // Klorin tinggi (>0.10 ppm)
        );

        $this->assertSame(DialysisWaterPurificationMonitorService::STATUS_HALT_DIALYSIS, $result['status']);
        $this->assertFalse($result['is_safe_for_patient_treatment']);
        $this->assertStringContainsString('Chlorine', $result['breached_parameters'][0]);
    }

    public function test_bacterial_growth_action_level_triggers_disinfection(): void
    {
        $result = $this->service->evaluateDialysisWaterQuality(
            'RO-LOOP-WEST-WING',
            8.0,
            65.0, // 50-100 CFU (action level)
            0.05,
            0.02
        );

        $this->assertSame(DialysisWaterPurificationMonitorService::STATUS_ACTION_REQUIRED, $result['status']);
        $this->assertTrue($result['disinfection_protocol_required']);
    }
}
