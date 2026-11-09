<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Events;

use Modules\Hospital\Domain\Models\Admission;

final class AdmissionDischargedEvent
{
    public function __construct(
        public readonly Admission $admission,
        public readonly string $dischargeDisposition,
        public readonly int $actorId
    ) {}
}
