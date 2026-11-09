<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Events;

use Modules\Hospital\Domain\Models\Admission;

final class AdmissionCreatedEvent
{
    public function __construct(
        public readonly Admission $admission,
        public readonly int $actorId
    ) {}
}
