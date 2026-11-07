<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\DTOs;

final class FacialSpoofAnalysisDTO
{
    /**
     * @param  array<int, string>  $detectedSpoofIndicators
     */
    public function __construct(
        public readonly bool $isGenuine,
        public readonly float $livenessConfidence,
        public readonly string $primaryClassification,
        public readonly array $detectedSpoofIndicators,
        public readonly bool $hardwareSensorHealthy
    ) {}

    public function toArray(): array
    {
        return [
            'is_genuine' => $this->isGenuine,
            'liveness_confidence' => $this->livenessConfidence,
            'classification' => $this->primaryClassification,
            'indicators' => $this->detectedSpoofIndicators,
            'sensor_healthy' => $this->hardwareSensorHealthy,
        ];
    }
}
