<?php

declare(strict_types=1);

namespace Modules\Hospital\Tests\Unit;

use Modules\Hospital\Domain\Services\EmergencyTriageEsiAlgorithmService;
use PHPUnit\Framework\TestCase;

class EmergencyTriageEsiAlgorithmServiceTest extends TestCase
{
    private EmergencyTriageEsiAlgorithmService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmergencyTriageEsiAlgorithmService();
    }

    public function test_resuscitation_immediate_life_saving_is_esi_level_1(): void
    {
        $result = $this->service->evaluateEsiTriage(
            true, // Requires immediate life saving (apnea/arrest)
            false,
            ['LAB', 'IV_HYDRATION', 'SPECIALTY_CONSULT'],
            140,
            32,
            78.0,
            55
        );

        $this->assertSame(EmergencyTriageEsiAlgorithmService::ESI_LEVEL_1_RESUSCITATION, $result['esi_level']);
        $this->assertSame(0, $result['target_response_time_minutes']);
        $this->assertSame('RED_RESUSCITATION', $result['acuity_badge']);
        $this->assertSame('RESUSCITATION_ROOM_BAY_1', $result['recommended_bed_zone']);
    }

    public function test_emergent_high_risk_is_esi_level_2(): void
    {
        $result = $this->service->evaluateEsiTriage(
            false,
            true, // High risk chest pain / confusion
            ['LAB', 'XRAY_CT'],
            95,
            18,
            97.0,
            62
        );

        $this->assertSame(EmergencyTriageEsiAlgorithmService::ESI_LEVEL_2_EMERGENT, $result['esi_level']);
        $this->assertSame(10, $result['target_response_time_minutes']);
        $this->assertSame('ORANGE_EMERGENT', $result['acuity_badge']);
    }

    public function test_stable_multiple_resources_is_esi_level_3(): void
    {
        $result = $this->service->evaluateEsiTriage(
            false,
            false,
            ['LAB', 'XRAY_CT', 'IV_HYDRATION'], // 3 resources
            82,
            16,
            99.0, // Normal vitals
            30
        );

        $this->assertSame(EmergencyTriageEsiAlgorithmService::ESI_LEVEL_3_URGENT, $result['esi_level']);
        $this->assertSame(30, $result['target_response_time_minutes']);
        $this->assertSame('YELLOW_URGENT', $result['acuity_badge']);
    }

    public function test_vital_sign_breach_upgrades_level_3_to_level_2(): void
    {
        $result = $this->service->evaluateEsiTriage(
            false,
            false,
            ['LAB', 'XRAY_CT'],
            135, // Heart rate > 120 (danger vital sign)
            22,
            95.0,
            40
        );

        $this->assertSame(EmergencyTriageEsiAlgorithmService::ESI_LEVEL_2_EMERGENT, $result['esi_level']);
        $this->assertTrue($result['is_danger_vital_signs_breached']);
    }

    public function test_non_urgent_no_resource_is_esi_level_5(): void
    {
        $result = $this->service->evaluateEsiTriage(
            false,
            false,
            [], // No resource (prescription refill)
            72,
            14,
            98.0,
            25
        );

        $this->assertSame(EmergencyTriageEsiAlgorithmService::ESI_LEVEL_5_NON_URGENT, $result['esi_level']);
        $this->assertSame(120, $result['target_response_time_minutes']);
    }
}
