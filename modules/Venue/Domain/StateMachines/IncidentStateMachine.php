<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;
use Modules\Venue\Domain\Models\CrowdCrushIncident;

final class IncidentStateMachine
{
    public const STATUS_REPORTED = 'reported';

    public const STATUS_ASSESSING = 'assessing';

    public const STATUS_ACTION_PENDING = 'action_pending';

    public const STATUS_LOCKDOWN_ACTIVE = 'lockdown_active';

    public const STATUS_EVACUATING = 'evacuating';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_REPORTED => [
            self::STATUS_ASSESSING,
            self::STATUS_CLOSED,
        ],
        self::STATUS_ASSESSING => [
            self::STATUS_ACTION_PENDING,
            self::STATUS_LOCKDOWN_ACTIVE,
            self::STATUS_RESOLVED,
        ],
        self::STATUS_ACTION_PENDING => [
            self::STATUS_LOCKDOWN_ACTIVE,
            self::STATUS_EVACUATING,
            self::STATUS_RESOLVED,
        ],
        self::STATUS_LOCKDOWN_ACTIVE => [
            self::STATUS_EVACUATING,
            self::STATUS_RESOLVED,
        ],
        self::STATUS_EVACUATING => [
            self::STATUS_RESOLVED,
        ],
        self::STATUS_RESOLVED => [
            self::STATUS_CLOSED,
        ],
        self::STATUS_CLOSED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function transition(CrowdCrushIncident $incident, string $targetStatus, ?string $reason = null): void
    {
        $current = $incident->status;

        if (! self::canTransition($current, $targetStatus)) {
            throw new DomainException("Transisi insiden kerumunan tidak sah dari '{$current}' ke '{$targetStatus}'");
        }

        if ($targetStatus === self::STATUS_LOCKDOWN_ACTIVE && ! $incident->lockdown_initiated_at) {
            $incident->lockdown_initiated_at = now();
        }

        $incident->status = $targetStatus;
        $incident->save();
    }
}
