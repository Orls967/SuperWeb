<?php

declare(strict_types=1);

namespace Tests\Unit\Algorithms;

use DomainException;
use Modules\Mining\Domain\Algorithms\NeedTimeTruckDispatcher;
use Modules\Mining\Domain\Algorithms\NeedTimeTruckDispatcherCertificateVerifier;
use Modules\Mining\Domain\Algorithms\NeedTimeTruckDispatcherResult;
use PHPUnit\Framework\TestCase;

class NeedTimeTruckDispatcherTest extends TestCase
{
    public function test_need_time_truck_dispatcher_prompt_vector(): void
    {
        $dispatcher = new NeedTimeTruckDispatcher();

        $shovels = [
            [
                'id' => 'S1',
                'loading_duration' => 4,
                'travel_time_from_dump' => 10,
                'available_at' => 3,
            ],
            [
                'id' => 'S2',
                'loading_duration' => 5,
                'travel_time_from_dump' => 6,
                'available_at' => 8,
            ],
        ];

        $trucks = [
            ['id' => 'T1', 'ready_at_dump' => 0],
            ['id' => 'T2', 'ready_at_dump' => 1],
            ['id' => 'T3', 'ready_at_dump' => 2],
            ['id' => 'T4', 'ready_at_dump' => 4],
        ];

        $fleetParams = [
            'total_fleet_trucks' => 12,
            'total_fleet_shovels' => 3,
            'mean_loading_time' => 4,
            'mean_truck_cycle_time' => 36,
        ];

        $result = $dispatcher->dispatch($shovels, $trucks, 15, $fleetParams);

        $this->assertCount(4, $result->assignments);

        // T1 -> S2 (start 8, wait 2)
        $this->assertEquals('T1', $result->assignments[0]['truck_id']);
        $this->assertEquals('S2', $result->assignments[0]['shovel_id']);
        $this->assertEquals(8, $result->assignments[0]['start_loading_time']);
        $this->assertEquals(2, $result->assignments[0]['truck_wait_time']);

        // T2 -> S1 (start 11, idle 8)
        $this->assertEquals('T2', $result->assignments[1]['truck_id']);
        $this->assertEquals('S1', $result->assignments[1]['shovel_id']);
        $this->assertEquals(11, $result->assignments[1]['start_loading_time']);
        $this->assertEquals(8, $result->assignments[1]['shovel_idle_time']);

        // T3 -> S2 (start 13, wait 5)
        $this->assertEquals('T3', $result->assignments[2]['truck_id']);
        $this->assertEquals('S2', $result->assignments[2]['shovel_id']);
        $this->assertEquals(13, $result->assignments[2]['start_loading_time']);
        $this->assertEquals(5, $result->assignments[2]['truck_wait_time']);

        // T4 -> S1 (start 15, wait 1)
        $this->assertEquals('T4', $result->assignments[3]['truck_id']);
        $this->assertEquals('S1', $result->assignments[3]['shovel_id']);
        $this->assertEquals(15, $result->assignments[3]['start_loading_time']);
        $this->assertEquals(1, $result->assignments[3]['truck_wait_time']);

        // Aggregates
        $this->assertEquals(8, $result->totalTruckWaitTime);
        $this->assertEquals(8, $result->totalShovelIdleTime);

        // Match Factor 4/9 (undertrucked)
        $this->assertEquals(4, $result->matchFactor['numerator']);
        $this->assertEquals(9, $result->matchFactor['denominator']);
        $this->assertEquals('4/9', $result->matchFactor['formatted']);
        $this->assertTrue($result->matchFactor['is_undertrucked']);
    }

    public function test_corrupted_certificate_throws_domain_exception(): void
    {
        $verifier = new NeedTimeTruckDispatcherCertificateVerifier();

        $corruptResult = new NeedTimeTruckDispatcherResult(
            assignments: [
                [
                    'truck_id' => 'T1',
                    'shovel_id' => 'S1',
                    'departure_time' => 0,
                    'travel_time' => 10,
                    'arrival_time' => 10,
                    'shovel_ready_time' => 0,
                    'start_loading_time' => 5, // invalid: max(10, 0) should be 10!
                    'loading_duration' => 4,
                    'finish_loading_time' => 9,
                    'truck_wait_time' => 0,
                    'shovel_idle_time' => 0,
                ],
            ],
            totalTruckWaitTime: 0,
            totalShovelIdleTime: 0,
            matchFactor: ['numerator' => 1, 'denominator' => 1, 'formatted' => '1/1', 'is_undertrucked' => false, 'is_overtrucked' => false, 'recommendation' => ''],
            totalActiveTrucks: 1,
            totalAvailableTrucks: 5,
            certificateHash: 'corrupted_hash',
            certificateData: []
        );

        $this->expectException(DomainException::class);
        $verifier->verify($corruptResult);
    }
}

