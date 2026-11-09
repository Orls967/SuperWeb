<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\HospitalInfectionControlSurveillanceService;
use PHPUnit\Framework\TestCase;

class HospitalInfectionControlSurveillanceServiceTest extends TestCase
{
    private HospitalInfectionControlSurveillanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HospitalInfectionControlSurveillanceService;
    }

    public function test_normal_infection_rate_within_cdc_benchmark(): void
    {
        // 1 kasus VAP dari 800 hari ventilator = 1.25 per 1.000 ventilator-days (target <= 2.5)
        $result = $this->service->calculateInfectionRate(
            'ICU-MAIN',
            HospitalInfectionControlSurveillanceService::INFECTION_TYPE_VAP,
            1,
            800,
            false
        );

        $this->assertSame(1.25, $result['incident_rate_per_thousand_days']);
        $this->assertSame(HospitalInfectionControlSurveillanceService::ALERT_LEVEL_GREEN_NORMAL, $result['alert_level']);
        $this->assertFalse($result['is_outbreak_declared']);
        $this->assertFalse($result['requires_cohorting_isolation']);
    }

    public function test_elevated_rate_triggers_outbreak_declaration(): void
    {
        // 6 kasus VAP dari 1000 hari = 6.0 per 1000 ventilator-days (> 5.0 outbreak threshold)
        $result = $this->service->calculateInfectionRate(
            'ICU-NORTH',
            HospitalInfectionControlSurveillanceService::INFECTION_TYPE_VAP,
            6,
            1000,
            false
        );

        $this->assertSame(6.0, $result['incident_rate_per_thousand_days']);
        $this->assertSame(HospitalInfectionControlSurveillanceService::ALERT_LEVEL_RED_OUTBREAK, $result['alert_level']);
        $this->assertTrue($result['is_outbreak_declared']);
        $this->assertTrue($result['requires_cohorting_isolation']);
        $this->assertStringContainsString('DEKLARASI OUTBREAK', $result['infection_control_actions'][0]);
    }

    public function test_mdro_isolation_forces_cohorting_precautions(): void
    {
        // 2 kasus dengan isolat MRSA / CRE
        $result = $this->service->calculateInfectionRate(
            'WARD-SURGICAL-2B',
            HospitalInfectionControlSurveillanceService::INFECTION_TYPE_SSI,
            2,
            1500,
            true // MDRO isolated
        );

        $this->assertTrue($result['requires_cohorting_isolation']);
        $this->assertSame(HospitalInfectionControlSurveillanceService::ALERT_LEVEL_RED_OUTBREAK, $result['alert_level']);
    }
}
