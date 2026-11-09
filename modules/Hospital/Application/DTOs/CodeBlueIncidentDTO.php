<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class CodeBlueIncidentDTO
{
    /**
     * @param  list<string>  $cprActionsPerformed
     * @param  list<int>  $responderUserIds
     * @param  array<string, mixed>  $medicationsAdministered
     */
    public function __construct(
        public readonly int $codeBlueEventId,
        public readonly int $admissionId,
        public readonly int $patientId,
        public readonly string $locationZone,
        public readonly string $initialRhythm, // VF, VT, ASYSTOLE, PEA
        public readonly int $responseDurationSeconds,
        public readonly int $totalCprMinutes,
        public readonly int $shocksDelivered,
        public readonly bool $roscAchieved, // Return of Spontaneous Circulation
        /** @var list<string> */
        public readonly array $cprActionsPerformed = [],
        /** @var list<int> */
        public readonly array $responderUserIds = [],
        public readonly array $medicationsAdministered = [],
        public readonly ?string $finalOutcome = 'TRANSFERRED_ICU'
    ) {}
}
