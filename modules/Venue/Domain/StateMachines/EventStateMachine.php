<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\StateMachines;

use DomainException;
use Modules\Venue\Domain\Enums\EventStatus;
use Modules\Venue\Domain\Models\EntertainmentEvent;

final class EventStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        EventStatus::DRAFT->value => [
            EventStatus::PERMIT_PENDING->value,
            EventStatus::CANCELLED->value,
        ],
        EventStatus::PERMIT_PENDING->value => [
            EventStatus::ON_SALE->value,
            EventStatus::CANCELLED->value,
        ],
        EventStatus::ON_SALE->value => [
            EventStatus::SOLD_OUT->value,
            EventStatus::LIVE->value,
            EventStatus::CLOSED->value,
            EventStatus::CANCELLED->value,
        ],
        EventStatus::SOLD_OUT->value => [
            EventStatus::LIVE->value,
            EventStatus::CANCELLED->value,
        ],
        EventStatus::LIVE->value => [
            EventStatus::CLOSED->value,
            EventStatus::CANCELLED->value,
        ],
        EventStatus::CLOSED->value => [
            EventStatus::SETTLED->value,
        ],
        EventStatus::SETTLED->value => [],
        EventStatus::CANCELLED->value => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $normalizedFrom = match (strtoupper($from)) {
            'OPEN' => EventStatus::ON_SALE->value,
            default => strtolower($from),
        };
        $normalizedTo = strtolower($to);

        return in_array($normalizedTo, self::TRANSITIONS[$normalizedFrom] ?? [], true);
    }

    public static function transition(EntertainmentEvent $event, EventStatus $targetStatus, ?string $reason = null): void
    {
        $current = $event->status;
        $target = $targetStatus->value;

        if (! self::canTransition($current, $target)) {
            throw new DomainException("Transisi event tidak sah dari '{$current}' ke '{$target}'");
        }

        // Guard rules
        if ($targetStatus === EventStatus::ON_SALE) {
            $hasApprovedPermit = $event->permits()->where('status', 'APPROVED')->exists();
            if (! $hasApprovedPermit && $event->permits()->count() > 0) {
                throw new DomainException('Tidak dapat membuka penjualan: izin keramaian belum disetujui');
            }
        }

        if ($targetStatus === EventStatus::CLOSED) {
            $event->doors_closed_at = now();
        }

        if ($targetStatus === EventStatus::SETTLED) {
            $event->settled_at = now();
        }

        $event->status = $target;
        $event->save();
    }
}
