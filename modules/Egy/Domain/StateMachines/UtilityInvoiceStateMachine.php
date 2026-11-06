<?php

declare(strict_types=1);

namespace Modules\Egy\Domain\StateMachines;

use DomainException;
use Modules\Egy\Domain\Models\EgyStatusTransition;
use Modules\Egy\Domain\Models\UtilityConsolidatedInvoice;

/**
 * State Machine for Utility Consolidated Invoice (8 statuses).
 * Transitions:
 * draft -> validated -> issued -> partially_paid -> paid
 * issued -> overdue -> disputed -> adjusted
 * issued -> partially_paid -> overdue -> disputed -> adjusted
 */
final class UtilityInvoiceStateMachine
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_ADJUSTED = 'adjusted';

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_VALIDATED, self::STATUS_ISSUED],
        self::STATUS_VALIDATED => [self::STATUS_ISSUED, self::STATUS_DRAFT],
        self::STATUS_ISSUED => [self::STATUS_PARTIALLY_PAID, self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_DISPUTED, self::STATUS_ADJUSTED],
        self::STATUS_PARTIALLY_PAID => [self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_DISPUTED, self::STATUS_ADJUSTED],
        self::STATUS_OVERDUE => [self::STATUS_PAID, self::STATUS_PARTIALLY_PAID, self::STATUS_DISPUTED, self::STATUS_ADJUSTED],
        self::STATUS_DISPUTED => [self::STATUS_ADJUSTED, self::STATUS_ISSUED, self::STATUS_PAID],
        self::STATUS_PAID => [self::STATUS_ADJUSTED],
        self::STATUS_ADJUSTED => [self::STATUS_ISSUED, self::STATUS_PAID],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    public static function transition(
        UtilityConsolidatedInvoice $invoice,
        string $newStatus,
        ?string $reason = null,
        ?array $metadata = null,
        ?string $triggeredBy = null
    ): UtilityConsolidatedInvoice {
        $current = $invoice->status;
        if ($current === $newStatus) {
            return $invoice;
        }

        if (! self::canTransition($current, $newStatus)) {
            throw new DomainException("Transisi status invoice tidak sah: dari '{$current}' ke '{$newStatus}'");
        }

        $invoice->status = $newStatus;
        $invoice->save();

        EgyStatusTransition::record(
            entityType: 'utility_invoice',
            entityId: (string) $invoice->id,
            fromStatus: $current,
            toStatus: $newStatus,
            reason: $reason,
            metadata: $metadata,
            triggeredBy: $triggeredBy
        );

        return $invoice;
    }
}
