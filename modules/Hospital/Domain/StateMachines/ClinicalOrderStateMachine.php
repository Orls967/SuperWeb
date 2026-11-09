<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\StateMachines;

use DomainException;
use Modules\Hospital\Domain\Models\HospitalOrder;
use Modules\Hospital\Domain\Models\ClinicalOrderTransition;

/**
 * State Machine untuk siklus hidup order klinis dokter (7 status).
 * ordered -> scheduled -> in_progress -> completed -> delayed -> cancelled -> escalated
 */
final class ClinicalOrderStateMachine
{
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DELAYED = 'delayed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ESCALATED = 'escalated';

    private const TRANSITIONS = [
        self::STATUS_ORDERED => [self::STATUS_SCHEDULED, self::STATUS_CANCELLED, self::STATUS_IN_PROGRESS],
        self::STATUS_SCHEDULED => [self::STATUS_IN_PROGRESS, self::STATUS_DELAYED, self::STATUS_CANCELLED],
        self::STATUS_DELAYED => [self::STATUS_IN_PROGRESS, self::STATUS_ESCALATED, self::STATUS_CANCELLED],
        self::STATUS_ESCALATED => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_DELAYED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $fromNorm = strtolower($from);
        $toNorm = strtolower($to);
        $allowed = self::TRANSITIONS[$fromNorm] ?? [];
        return in_array($toNorm, $allowed, true);
    }

    public static function transition(
        HospitalOrder $order,
        string $toStatus,
        int $delayMinutes = 0,
        ?string $note = null,
        ?int $actorId = null
    ): void {
        $fromStatus = strtolower($order->status);
        $targetStatus = strtolower($toStatus);

        if (! self::canTransition($fromStatus, $targetStatus)) {
            throw new DomainException("Transisi order klinis tidak sah dari '{$fromStatus}' ke '{$targetStatus}'.");
        }

        $order->status = strtoupper($targetStatus);
        $order->save();

        ClinicalOrderTransition::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $targetStatus,
            'delay_minutes' => $delayMinutes,
            'note' => $note,
            'actor_id' => $actorId,
            'transitioned_at' => now(),
        ]);
    }
}
