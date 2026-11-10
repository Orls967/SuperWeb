<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\Events;

use JsonSerializable;

/**
 * Event diterbitkan saat status kontrak forward berpindah melalui state machine.
 */
final class HedgeContractTransitionedEvent implements JsonSerializable
{
    public const EVENT_NAME = 'treasury.hedge_contract.transitioned.v1';

    public function __construct(
        public string $contractId,
        public string $contractNumber,
        public string $fromStatus,
        public string $toStatus,
        public ?int $actorId = null,
        public string $reason = ''
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'event' => self::EVENT_NAME,
            'contract_id' => $this->contractId,
            'contract_number' => $this->contractNumber,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'actor_id' => $this->actorId,
            'reason' => $this->reason,
        ];
    }
}
