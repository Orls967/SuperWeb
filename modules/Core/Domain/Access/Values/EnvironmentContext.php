<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Values;

use DateTimeImmutable;

/**
 * Immutable context representing environmental factors during an access evaluation (W8-D53).
 *
 * Captures temporal conditions, network zones, business hours, and operational status.
 */
final class EnvironmentContext
{
    /**
     * @param  DateTimeImmutable  $timestamp  Exact timestamp of the evaluation request
     * @param  bool  $isBusinessHours  Whether current time falls within standard operating hours (08:00 - 18:00 WIB)
     * @param  bool  $isWeekend  Whether request occurs on Saturday or Sunday
     * @param  bool  $isEmergencyMaintenanceWindow  Whether system is undergoing an active scheduled or emergency maintenance window
     * @param  string  $securityZone  Corporate network zone (e.g. 'INTERNAL_LAN', 'DMZ', 'PUBLIC_INTERNET', 'SECURE_BASTION')
     * @param  string  $threatLevel  Current active threat level ('GREEN', 'YELLOW', 'ORANGE', 'RED')
     * @param  array<string, mixed>  $additionalFactors  Dynamic contextual metrics
     */
    public function __construct(
        public readonly DateTimeImmutable $timestamp,
        public readonly bool $isBusinessHours = true,
        public readonly bool $isWeekend = false,
        public readonly bool $isEmergencyMaintenanceWindow = false,
        public readonly string $securityZone = 'PUBLIC_INTERNET',
        public readonly string $threatLevel = 'GREEN',
        public readonly array $additionalFactors = []
    ) {}

    /**
     * Factory from current system time and default network factors.
     */
    public static function createCurrent(
        string $securityZone = 'PUBLIC_INTERNET',
        string $threatLevel = 'GREEN'
    ): self {
        $now = new DateTimeImmutable;
        $hour = (int) $now->format('G');
        $dayOfWeek = (int) $now->format('N'); // 1 (Mon) to 7 (Sun)

        $isBusinessHours = ($hour >= 8 && $hour < 18) && ($dayOfWeek >= 1 && $dayOfWeek <= 5);
        $isWeekend = ($dayOfWeek >= 6);

        return new self(
            timestamp: $now,
            isBusinessHours: $isBusinessHours,
            isWeekend: $isWeekend,
            isEmergencyMaintenanceWindow: false,
            securityZone: $securityZone,
            threatLevel: $threatLevel
        );
    }

    /**
     * Check if request originates from trusted internal zone.
     */
    public function isTrustedInternalZone(): bool
    {
        return in_array($this->securityZone, ['INTERNAL_LAN', 'SECURE_BASTION'], true);
    }

    /**
     * Export as flat evaluation dictionary for condition matching.
     *
     * @return array<string, mixed>
     */
    public function toEvaluationArray(): array
    {
        return array_merge($this->additionalFactors, [
            'timestamp_iso' => $this->timestamp->format('c'),
            'hour_of_day' => (int) $this->timestamp->format('G'),
            'day_of_week' => (int) $this->timestamp->format('N'),
            'is_business_hours' => $this->isBusinessHours,
            'is_weekend' => $this->isWeekend,
            'is_emergency_maintenance' => $this->isEmergencyMaintenanceWindow,
            'security_zone' => $this->securityZone,
            'threat_level' => $this->threatLevel,
        ]);
    }
}
