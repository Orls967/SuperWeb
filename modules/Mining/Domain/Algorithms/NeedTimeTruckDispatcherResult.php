<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Algorithms;

final class NeedTimeTruckDispatcherResult
{
    /**
     * @param  list<array{
     *     truck_id: string,
     *     shovel_id: string,
     *     departure_time: int,
     *     travel_time: int,
     *     arrival_time: int,
     *     shovel_ready_time: int,
     *     start_loading_time: int,
     *     loading_duration: int,
     *     finish_loading_time: int,
     *     truck_wait_time: int,
     *     shovel_idle_time: int
     * }>  $assignments
     * @param  array{numerator: int, denominator: int, formatted: string, is_undertrucked: bool, is_overtrucked: bool, recommendation: string}  $matchFactor
     */
    public function __construct(
        public readonly array $assignments,
        public readonly int $totalTruckWaitTime,
        public readonly int $totalShovelIdleTime,
        public readonly array $matchFactor,
        public readonly int $totalActiveTrucks,
        public readonly int $totalAvailableTrucks,
        public readonly string $certificateHash,
        public readonly array $certificateData
    ) {}
}

