<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;
use Modules\Venue\Domain\Models\TicketResale;

final class TicketResaleStateMachine
{
    public const STATUS_LISTED = 'LISTED';

    public const STATUS_ESCROW_HOLD = 'ESCROW_HOLD';

    public const STATUS_SETTLED = 'SETTLED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUS_EXPIRED = 'EXPIRED';

    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        self::STATUS_LISTED => [
            self::STATUS_ESCROW_HOLD,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
            self::STATUS_SETTLED, // Direct settlement when buyer confirms escrow immediately
        ],
        self::STATUS_ESCROW_HOLD => [
            self::STATUS_SETTLED,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_SETTLED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_EXPIRED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $fromUpper = strtoupper($from);
        $toUpper = strtoupper($to);

        $allowed = self::TRANSITIONS[$fromUpper] ?? [];

        return in_array($toUpper, $allowed, true);
    }

    public static function transition(TicketResale $resale, string $to): TicketResale
    {
        $current = strtoupper((string) $resale->status);
        $target = strtoupper($to);

        if (! self::canTransition($current, $target)) {
            throw new DomainException(sprintf('Transisi status penjualan tiket dari %s ke %s tidak diizinkan', $current, $target));
        }

        $resale->status = $target;

        return $resale;
    }
}
