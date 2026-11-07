<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Blasting;

final readonly class ExplosiveProperty
{
    public function __construct(
        public string $explosiveType,          // ANFO, HEAVY_ANFO, EMULSION
        public float $densityGPerCm3 = 0.85,  // Densitas ANFO ~0.85 g/cm3
        public float $relativeWeightStrengthRws = 100.0, // ANFO standard = 100
        public float $velocityOfDetonationMPerS = 4200.0 // Kecepatan detonasi VOD
    ) {}
}
