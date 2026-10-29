<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class VitalSignsDTO
{
    public function __construct(
        public int $systolic,
        public int $diastolic,
        public int $pulse,
        public int $respiratory,
        public float $temperature,
    ) {
    }
}
