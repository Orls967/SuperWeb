<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class AcousticNoiseTelemetry
{
    public function __construct(
        public readonly float $meanDecibels,
        public readonly float $peakDecibels,
        public readonly float $rateOfChangePerSecond,
        public readonly string $riskClassification
    ) {}

    public function isDangerousToAuditorySystem(): bool
    {
        return $this->peakDecibels >= 115.0 || $this->meanDecibels >= 100.0;
    }

    public function toArray(): array
    {
        return [
            'mean_decibels' => $this->meanDecibels,
            'peak_decibels' => $this->peakDecibels,
            'rate_of_change' => $this->rateOfChangePerSecond,
            'risk_classification' => $this->riskClassification,
            'auditory_hazard' => $this->isDangerousToAuditorySystem(),
        ];
    }
}
