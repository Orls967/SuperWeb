<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use Modules\Venue\Domain\Algorithms\Experience\WaitPerServiceGovernor;
use Tests\TestCase;

class WaitPerServiceGovernorTest extends TestCase
{
    private WaitPerServiceGovernor $governor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->governor = new WaitPerServiceGovernor;
    }

    public function test_detects_saturated_queue_and_demands_reinforcement(): void
    {
        // 3 servers, arrival 8/min, capacity 2/min/server -> utilization = 133% (saturated)
        $result = $this->governor->calculateWps(
            stationId: 10,
            activeServersCount: 3,
            arrivalRatePerMinuteBps: 8000,
            serviceRatePerMinuteBps: 2000,
            maxTolerableWaitMinutes: 15,
            saltSecret: 'UNIT_TEST_SALT'
        );

        $this->assertEquals(0, $result->wpsScore);
        $this->assertTrue($result->reinforcementNeeded);
        $this->assertGreaterThan(0, $result->recommendedAdditionalServers);
        $this->assertTrue($result->verifySignature('UNIT_TEST_SALT'));
    }

    public function test_high_wps_score_when_servers_are_sufficient(): void
    {
        // 6 servers, arrival 6/min, capacity 2/min/server -> utilization = 50%
        $result = $this->governor->calculateWps(
            stationId: 10,
            activeServersCount: 6,
            arrivalRatePerMinuteBps: 6000,
            serviceRatePerMinuteBps: 2000,
            maxTolerableWaitMinutes: 15,
            saltSecret: 'UNIT_TEST_SALT'
        );

        $this->assertGreaterThanOrEqual(80, $result->wpsScore);
        $this->assertFalse($result->reinforcementNeeded);
        $this->assertEquals(0, $result->recommendedAdditionalServers);
        $this->assertTrue($result->verifySignature('UNIT_TEST_SALT'));
    }
}
