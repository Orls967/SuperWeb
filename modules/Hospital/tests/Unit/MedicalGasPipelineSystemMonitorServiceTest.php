<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\MedicalGasPipelineSystemMonitorService;
use PHPUnit\Framework\TestCase;

class MedicalGasPipelineSystemMonitorServiceTest extends TestCase
{
    private MedicalGasPipelineSystemMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MedicalGasPipelineSystemMonitorService;
    }

    public function test_normal_operating_oxygen_pressure(): void
    {
        $result = $this->service->evaluatePipelineTelemetry(
            'ZONE-ICU-01',
            MedicalGasPipelineSystemMonitorService::GAS_TYPE_OXYGEN,
            4.45, // Dalam rentang 4.0 - 5.2
            60.0,
            4
        );

        $this->assertFalse($result['is_alarm_triggered']);
        $this->assertSame('NONE', $result['alarm_severity']);
        $this->assertSame('NORMAL_OPERATING_PRESSURE', $result['status']);
        $this->assertFalse($result['leak_suspicion']);
    }

    public function test_low_pressure_triggers_critical_alarm(): void
    {
        $result = $this->service->evaluatePipelineTelemetry(
            'ZONE-OR-SUITE-1',
            MedicalGasPipelineSystemMonitorService::GAS_TYPE_OXYGEN,
            3.20, // Di bawah 4.0 bar
            20.0,
            2
        );

        $this->assertTrue($result['is_alarm_triggered']);
        $this->assertSame('CRITICAL_LOW_PRESSURE', $result['alarm_severity']);
        $this->assertSame('LOW_PRESSURE_BREACH', $result['status']);
    }

    public function test_abnormal_high_flow_rate_triggers_leak_alarm(): void
    {
        $result = $this->service->evaluatePipelineTelemetry(
            'ZONE-WARD-3A',
            MedicalGasPipelineSystemMonitorService::GAS_TYPE_MEDICAL_AIR_4BAR,
            4.10,
            250.0, // Aliran sangat tinggi untuk 1 ventilator
            1
        );

        $this->assertTrue($result['leak_suspicion']);
        $this->assertTrue($result['is_alarm_triggered']);
        $this->assertSame('POSSIBLE_PIPE_RUPTURE', $result['alarm_severity']);
    }
}
