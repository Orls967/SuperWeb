<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;
use Modules\Hospital\Domain\Enums\SpecimenCustodyStatus;

class SpecimenCustodyStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        SpecimenCustodyStatus::COLLECTED->value => [
            SpecimenCustodyStatus::IN_TRANSIT->value,
            SpecimenCustodyStatus::REJECTED->value,
        ],
        SpecimenCustodyStatus::IN_TRANSIT->value => [
            SpecimenCustodyStatus::RECEIVED_LAB->value,
            SpecimenCustodyStatus::REJECTED->value,
        ],
        SpecimenCustodyStatus::RECEIVED_LAB->value => [
            SpecimenCustodyStatus::PROCESSED->value,
            SpecimenCustodyStatus::REJECTED->value,
        ],
        SpecimenCustodyStatus::PROCESSED->value => [
            SpecimenCustodyStatus::DISPOSED->value,
        ],
        SpecimenCustodyStatus::REJECTED->value => [
            SpecimenCustodyStatus::DISPOSED->value,
        ],
        SpecimenCustodyStatus::DISPOSED->value => [],
    ];

    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, self::ALLOWED_TRANSITIONS[$fromStatus] ?? [], true);
    }

    public static function assertCanTransition(string $fromStatus, string $toStatus): void
    {
        if (! self::canTransition($fromStatus, $toStatus)) {
            throw new DomainException(
                "Transisi status spesimen tidak sah dari [{$fromStatus}] ke [{$toStatus}]."
            );
        }
    }
}
