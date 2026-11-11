<?php

declare(strict_types=1);

namespace Modules\Core\Domain\StateMachines;

use DomainException;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Models\CoreEdiInterchange;
use Modules\Core\Domain\Models\CoreEdiTransition;

/**
 * State Machine for EDI Interchanges (8 statuses / W8-D49 / D55-1).
 *
 * received -> parsed -> validated -> mapped -> acknowledged -> posted -> rejected -> archived
 * Terminal state: archived. CONTRL/997 payload is persisted for every interchange.
 */
final class EdiInterchangeStateMachine
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PARSED = 'parsed';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_MAPPED = 'mapped';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ARCHIVED = 'archived';

    public const ALL_STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_PARSED,
        self::STATUS_VALIDATED,
        self::STATUS_MAPPED,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_POSTED,
        self::STATUS_REJECTED,
        self::STATUS_ARCHIVED,
    ];

    /**
     * Explicit allowed transition graph matching D55-1 specification.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_RECEIVED => [self::STATUS_PARSED, self::STATUS_REJECTED],
        self::STATUS_PARSED => [self::STATUS_VALIDATED, self::STATUS_REJECTED],
        self::STATUS_VALIDATED => [self::STATUS_MAPPED, self::STATUS_REJECTED],
        self::STATUS_MAPPED => [self::STATUS_ACKNOWLEDGED, self::STATUS_REJECTED],
        self::STATUS_ACKNOWLEDGED => [self::STATUS_POSTED, self::STATUS_REJECTED],
        self::STATUS_POSTED => [self::STATUS_ARCHIVED],
        self::STATUS_REJECTED => [self::STATUS_ARCHIVED],
        self::STATUS_ARCHIVED => [],
    ];

    public function __construct(private readonly ClockInterface $clock) {}

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Transition CoreEdiInterchange with strict business guards and append-only transition record.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws DomainException When transition or business guard fails.
     */
    public function transition(
        CoreEdiInterchange $interchange,
        string $targetStatus,
        string $reason = '',
        ?int $actorUserId = null,
        array $attributes = [],
    ): CoreEdiTransition {
        $fromStatus = (string) $interchange->status;
        if (! $this->canTransition($fromStatus, $targetStatus)) {
            throw new DomainException("EDI_TRANSITION_INVALID: Transisi status [{$fromStatus}] ke [{$targetStatus}] tidak diizinkan.");
        }

        if ($targetStatus === self::STATUS_ACKNOWLEDGED) {
            $contrl = (string) ($attributes['contrl_payload'] ?? $interchange->contrl_payload ?? '');
            if (trim($contrl) === '') {
                throw new DomainException('EDI_ACK_GUARD: Status acknowledged mewajibkan payload CONTRL/997 tercatat.');
            }
        }

        if ($targetStatus === self::STATUS_POSTED) {
            $handlerMod = (string) ($attributes['handler_module'] ?? $interchange->handler_module ?? '');
            $handlerKey = (string) ($attributes['handler_idempotency_key'] ?? $interchange->handler_idempotency_key ?? '');
            $targetRef = (string) ($attributes['target_reference'] ?? $interchange->target_reference ?? '');
            if ($handlerMod === '' || $handlerKey === '' || $targetRef === '') {
                throw new DomainException('EDI_POST_GUARD: Status posted dilarang tanpa panggilan nyata ke EdiDocumentHandler dan idempotency_key tercatat.');
            }
        }

        if ($targetStatus === self::STATUS_REJECTED && trim($reason) === '') {
            throw new DomainException('EDI_REJECT_GUARD: Penolakan interchange wajib mencantumkan alasan yang jelas.');
        }

        foreach ($attributes as $key => $value) {
            $interchange->setAttribute($key, $value);
        }

        if ($targetStatus === self::STATUS_REJECTED) {
            $interchange->rejection_reason = mb_substr(trim($reason), 0, 250);
        }

        $interchange->status = $targetStatus;
        $interchange->save();

        return CoreEdiTransition::create([
            'edi_interchange_id' => $interchange->id,
            'actor_user_id' => $actorUserId,
            'from_status' => $fromStatus,
            'to_status' => $targetStatus,
            'reason' => mb_substr($reason, 0, 250),
            'handler_module' => $interchange->handler_module,
            'transitioned_at' => $this->clock->now(),
        ]);
    }
}

