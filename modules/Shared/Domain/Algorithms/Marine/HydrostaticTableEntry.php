<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Marine;

final readonly class HydrostaticTableEntry
{
    public function __construct(
        public float $draftM,
        public float $displacementTon,
        public float $tpcTonPerCm = 15.0, // Tonnes per Centimetre immersion
        public float $lcfM = 0.0          // Longitudinal Centre of Flotation from midship
    ) {}
}
