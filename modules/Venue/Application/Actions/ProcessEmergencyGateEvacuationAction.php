<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Venue\Application\Services\EmergencyEvacuationCoordinatorService;

final class ProcessEmergencyGateEvacuationAction
{
    public function __construct(
        private readonly EmergencyEvacuationCoordinatorService $evacuationService
    ) {}

    /**
     * @return array{
     *     incident_code: string,
     *     status: string,
     *     unlocked_turnstiles: int,
     *     evacuated_zones: array<string>,
     *     dispatch_timestamp: string,
     *     siren_activated: bool
     * }
     */
    public function execute(int $venueId, string $triggerReason, string $authorizedBy): array
    {
        return $this->evacuationService->initiateFullEvacuation($venueId, $triggerReason, $authorizedBy);
    }
}
