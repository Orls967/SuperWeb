<?php

declare(strict_types=1);

namespace Modules\Wms\Domain\StateMachines;

use InvalidArgumentException;

/**
 * State machine 8 status untuk misi robotik gudang (Fase 279).
 */
final class RobotMissionStateMachine
{
    public const string STATE_QUEUED = 'queued';

    public const string STATE_ASSIGNED = 'assigned';

    public const string STATE_EN_ROUTE = 'en_route';

    public const string STATE_PICKING = 'picking';

    public const string STATE_DELIVERING = 'delivering';

    public const string STATE_COMPLETED = 'completed';

    public const string STATE_INTERLOCKED = 'interlocked';

    public const string STATE_FAILED_FALLBACK = 'failed_fallback';

    /** @var array<string, list<string>> */
    private const array TRANSITIONS = [
        self::STATE_QUEUED => [self::STATE_ASSIGNED, self::STATE_FAILED_FALLBACK],
        self::STATE_ASSIGNED => [self::STATE_EN_ROUTE, self::STATE_FAILED_FALLBACK],
        self::STATE_EN_ROUTE => [self::STATE_PICKING, self::STATE_INTERLOCKED, self::STATE_FAILED_FALLBACK],
        self::STATE_PICKING => [self::STATE_DELIVERING, self::STATE_INTERLOCKED, self::STATE_FAILED_FALLBACK],
        self::STATE_DELIVERING => [self::STATE_COMPLETED, self::STATE_INTERLOCKED, self::STATE_FAILED_FALLBACK],
        self::STATE_INTERLOCKED => [self::STATE_EN_ROUTE, self::STATE_FAILED_FALLBACK],
        self::STATE_FAILED_FALLBACK => [self::STATE_COMPLETED],
        self::STATE_COMPLETED => [],
    ];

    /**
     * @return list<string>
     */
    public static function allStates(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new InvalidArgumentException("Transisi misi robot tidak valid: {$from} -> {$to}.");
        }
    }
}

