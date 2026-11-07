<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Shared\Application\BaseAction;
use Modules\Venue\Application\Services\PartialWeatherZoneRelocationService;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentZone;

class RelocateZoneTicketsAction extends BaseAction
{
    public function __construct(
        private readonly PartialWeatherZoneRelocationService $relocationService
    ) {}

    /**
     * @return array{
     *     relocated_count: int,
     *     refunded_count: int,
     *     total_refund_idr: int,
     *     target_zone_occupancy: int
     * }
     */
    public function execute(
        EntertainmentEvent $event,
        EntertainmentZone $fromZone,
        EntertainmentZone $toZone,
        int $partialRefundBps = 2000
    ): array {
        return $this->relocationService->relocatePatronsFromClosedZone(
            event: $event,
            closedZone: $fromZone,
            targetIndoorZone: $toZone,
            partialRefundBps: $partialRefundBps
        );
    }
}
