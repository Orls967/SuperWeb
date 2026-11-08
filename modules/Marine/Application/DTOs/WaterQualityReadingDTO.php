<?php

declare(strict_types=1);

namespace Modules\Marine\Application\DTOs;

use DomainException;

/**
 * DTO pembacaan sensor telemetri kualitas air tambak IoT (Fase 171).
 */
class WaterQualityReadingDTO
{
    public function __construct(
        public readonly int $batchId,
        public readonly string $sensorDeviceUuid,
        public readonly float $dissolvedOxygenMgL,
        public readonly float $phLevel,
        public readonly float $waterTemperatureCelsius,
        public readonly float $salinityPpt,
        public readonly float $ammoniaNitrogenPpm,
        public readonly float $nitriteNo2Ppm,
        public readonly float $alkalinityMgL,
        public readonly float $orpMv,
        public readonly bool $aeratorActive = true,
        public readonly ?string $recordedAt = null
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->batchId <= 0) {
            throw new DomainException('Batch ID harus valid.');
        }

        if ($this->dissolvedOxygenMgL < 0.0 || $this->phLevel < 0.0 || $this->salinityPpt < 0.0) {
            throw new DomainException('Parameter fisik air tidak boleh negatif.');
        }
    }

    public function determineWaterQualityGrade(): string
    {
        if ($this->dissolvedOxygenMgL < 2.0 || $this->ammoniaNitrogenPpm > 0.5) {
            return 'CRITICAL_ANOXIA';
        }

        if ($this->dissolvedOxygenMgL < 4.0 || $this->phLevel < 7.2 || $this->phLevel > 8.8) {
            return 'STRESS_LEVEL';
        }

        if ($this->dissolvedOxygenMgL >= 5.0 && $this->phLevel >= 7.5 && $this->phLevel <= 8.5) {
            return 'OPTIMAL';
        }

        return 'ACCEPTABLE';
    }

    public function requiresImmediateAerationBoost(): bool
    {
        return $this->dissolvedOxygenMgL < 3.5;
    }
}
