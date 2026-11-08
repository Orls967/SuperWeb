<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\StateMachines;

use Modules\Aviation\Domain\Enums\MaintenanceVisitStatus;
use Modules\Aviation\Domain\Exceptions\InvalidStateTransitionException;

/**
 * State Machine untuk Kunjungan Perawatan Pesawat (7 status).
 * planned -> grounded -> in_work -> inspection -> released_to_service -> deferred -> closed
 */
final class MaintenanceVisitStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        MaintenanceVisitStatus::PLANNED->value => [
            MaintenanceVisitStatus::GROUNDED->value,
            MaintenanceVisitStatus::CLOSED->value,
        ],
        MaintenanceVisitStatus::GROUNDED->value => [
            MaintenanceVisitStatus::IN_WORK->value,
        ],
        MaintenanceVisitStatus::IN_WORK->value => [
            MaintenanceVisitStatus::INSPECTION->value,
        ],
        MaintenanceVisitStatus::INSPECTION->value => [
            MaintenanceVisitStatus::RELEASED_TO_SERVICE->value,
            MaintenanceVisitStatus::DEFERRED->value,
            MaintenanceVisitStatus::IN_WORK->value,
        ],
        MaintenanceVisitStatus::DEFERRED->value => [
            MaintenanceVisitStatus::RELEASED_TO_SERVICE->value,
            MaintenanceVisitStatus::IN_WORK->value,
        ],
        MaintenanceVisitStatus::RELEASED_TO_SERVICE->value => [
            MaintenanceVisitStatus::CLOSED->value,
        ],
        MaintenanceVisitStatus::CLOSED->value => [],
    ];

    public static function canTransition(string|MaintenanceVisitStatus $from, string|MaintenanceVisitStatus $to): bool
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        return in_array($toVal, self::TRANSITIONS[$fromVal] ?? [], true);
    }

    public static function assertCanTransition(string|MaintenanceVisitStatus $from, string|MaintenanceVisitStatus $to): void
    {
        $fromVal = is_string($from) ? $from : $from->value;
        $toVal = is_string($to) ? $to : $to->value;

        if (! self::canTransition($fromVal, $toVal)) {
            throw InvalidStateTransitionException::make('MaintenanceVisit', $fromVal, $toVal);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return self::TRANSITIONS;
    }
}
