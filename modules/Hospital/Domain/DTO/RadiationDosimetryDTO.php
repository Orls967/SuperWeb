<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\DTO;

readonly class RadiationDosimetryDTO
{
    public function __construct(
        public int $studyId,
        public float $ctdiVolMgy,
        public float $scanLengthCm,
        public ?float $patientDiameterCm = null
    ) {}
}
