<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final class MiceProposalCreatedEvent
{
    public function __construct(
        public readonly string $proposalId,
        public readonly string $proposalNumber,
        public readonly string $clientName,
        public readonly int $totalAmount,
        public readonly float $grossMarginPercent,
        public readonly string $createdAt
    ) {}
}
