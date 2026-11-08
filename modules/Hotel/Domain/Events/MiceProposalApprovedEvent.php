<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final class MiceProposalApprovedEvent
{
    public function __construct(
        public readonly string $proposalId,
        public readonly string $proposalNumber,
        public readonly string $approvedBy,
        public readonly float $finalMarginPercent
    ) {}
}
