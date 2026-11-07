<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

final class OpticalFlowVector
{
    public function __construct(
        public readonly float $meanVelocityX,
        public readonly float $meanVelocityY,
        public readonly float $turbulenceIndex,
        public readonly string $flowRegime
    ) {}

    public function isDangerousTurbulence(): bool
    {
        return $this->turbulenceIndex > 0.75 || $this->flowRegime === 'TURBULENT_CROWD_CRUSH_PRECURSOR';
    }

    public function toArray(): array
    {
        return [
            'vx' => $this->meanVelocityX,
            'vy' => $this->meanVelocityY,
            'turbulence' => $this->turbulenceIndex,
            'flow_regime' => $this->flowRegime,
            'is_dangerous' => $this->isDangerousTurbulence(),
        ];
    }
}
