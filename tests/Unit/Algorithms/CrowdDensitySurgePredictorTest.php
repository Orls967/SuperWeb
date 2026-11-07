<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Venue\Domain\Algorithms\CrowdSafety\CrowdDensitySurgePredictor;
use Tests\TestCase;

class CrowdDensitySurgePredictorTest extends TestCase
{
    private CrowdDensitySurgePredictor $predictor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->predictor = new CrowdDensitySurgePredictor;
    }

    public function test_predicts_crowd_surge_and_restricts_gate_access(): void
    {
        // 450 current in 500 limit, net inflow 8/min -> 15m projected = 450 + 120 = 570 (> 500)
        $result = $this->predictor->predictSurge(
            zoneId: 1,
            currentHeadcount: 450,
            zoneAreaSqm: 100,
            legalCapacityLimit: 500,
            inflowVelocityPerMinute: 10,
            outflowVelocityPerMinute: 2,
            weatherFactorBps: 10000,
            saltSecret: 'UNIT_TEST_SALT'
        );

        $this->assertEquals(570, $result->predictedHeadcount15m);
        $this->assertEquals('RESTRICT_ACCESS', $result->recommendedAction);
        $this->assertTrue($result->gateRestricted);
        $this->assertTrue($result->verifySignature('UNIT_TEST_SALT'));
    }

    public function test_normal_operation_when_flow_is_balanced(): void
    {
        // 200 current in 500 limit, net flow 0
        $result = $this->predictor->predictSurge(
            zoneId: 1,
            currentHeadcount: 200,
            zoneAreaSqm: 100,
            legalCapacityLimit: 500,
            inflowVelocityPerMinute: 5,
            outflowVelocityPerMinute: 5,
            weatherFactorBps: 10000,
            saltSecret: 'UNIT_TEST_SALT'
        );

        $this->assertEquals(200, $result->predictedHeadcount15m);
        $this->assertEquals('NORMAL', $result->recommendedAction);
        $this->assertFalse($result->gateRestricted);
        $this->assertTrue($result->verifySignature('UNIT_TEST_SALT'));
    }
}
