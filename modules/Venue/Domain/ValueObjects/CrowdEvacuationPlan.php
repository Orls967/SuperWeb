<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class CrowdEvacuationPlan
{
    /**
     * @param  array<int, array<string, mixed>>  $zoneSimulations
     * @param  array<int, array<string, mixed>>  $bottlenecks
     */
    public function __construct(
        public readonly int $venueId,
        public readonly int $eventId,
        public readonly int $totalEvacuees,
        public readonly float $estimatedTotalMinutes,
        public readonly string $complianceStatus,
        public readonly array $zoneSimulations,
        public readonly array $bottlenecks
    ) {}

    public function isRegulatoryCompliant(): bool
    {
        return $this->complianceStatus === 'COMPLIANT';
    }

    public function toArray(): array
    {
        return [
            'venue_id' => $this->venueId,
            'event_id' => $this->eventId,
            'total_evacuees' => $this->totalEvacuees,
            'estimated_total_minutes' => $this->estimatedTotalMinutes,
            'compliance_status' => $this->complianceStatus,
            'zone_simulations' => $this->zoneSimulations,
            'bottlenecks' => $this->bottlenecks,
        ];
    }
}
