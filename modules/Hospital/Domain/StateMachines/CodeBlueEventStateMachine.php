<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;
use Modules\Hospital\Domain\Models\CodeBlueEvent;

/**
 * State Machine untuk peristiwa kegawatdaruratan Code Blue (7 status).
 * detected -> raised -> acknowledged -> team_dispatched -> resolved -> escalated -> closed
 */
final class CodeBlueEventStateMachine
{
    public const STATUS_DETECTED = 'detected';

    public const STATUS_RAISED = 'raised';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_TEAM_DISPATCHED = 'team_dispatched';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_CLOSED = 'closed';

    private const TRANSITIONS = [
        self::STATUS_DETECTED => [self::STATUS_RAISED, self::STATUS_CLOSED],
        self::STATUS_RAISED => [self::STATUS_ACKNOWLEDGED, self::STATUS_TEAM_DISPATCHED, self::STATUS_ESCALATED],
        self::STATUS_ACKNOWLEDGED => [self::STATUS_TEAM_DISPATCHED, self::STATUS_RESOLVED, self::STATUS_ESCALATED],
        self::STATUS_TEAM_DISPATCHED => [self::STATUS_RESOLVED, self::STATUS_ESCALATED],
        self::STATUS_ESCALATED => [self::STATUS_RESOLVED, self::STATUS_TEAM_DISPATCHED],
        self::STATUS_RESOLVED => [self::STATUS_CLOSED],
        self::STATUS_CLOSED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $fromNorm = strtolower($from);
        $toNorm = strtolower($to);
        $allowed = self::TRANSITIONS[$fromNorm] ?? [];

        return in_array($toNorm, $allowed, true);
    }

    public static function transition(
        CodeBlueEvent $event,
        string $toStatus,
        ?string $reason = null,
        ?int $actorId = null
    ): void {
        $fromStatus = strtolower($event->status);
        $targetStatus = strtolower($toStatus);

        if (! self::canTransition($fromStatus, $targetStatus)) {
            throw new DomainException("Transisi Code Blue tidak sah dari '{$fromStatus}' ke '{$targetStatus}'.");
        }

        $event->status = $targetStatus;
        if ($targetStatus === self::STATUS_ACKNOWLEDGED && $event->nurse_ack_at === null) {
            $event->nurse_ack_at = now();
        }
        if ($targetStatus === self::STATUS_TEAM_DISPATCHED && $event->team_ack_at === null) {
            $event->team_ack_at = now();
        }
        if ($targetStatus === self::STATUS_RESOLVED) {
            $event->resolved_at = now();
        }
        $event->save();
    }
}
