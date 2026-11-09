<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Events;

use Modules\Hospital\Domain\Models\HospitalBed;

final class BedAssignedEvent
{
    public function __construct(
        public readonly HospitalBed $bed,
        public readonly int $patientId,
        public readonly int $admissionId,
        public readonly int $actorId
    ) {}
}
