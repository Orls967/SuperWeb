<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Events;

final class MiceContractSignedEvent
{
    /**
     * @param  array<string, mixed>  $contractTerms
     */
    public function __construct(
        public readonly string $contractId,
        public readonly string $contractNumber,
        public readonly string $clientName,
        public readonly float $contractValue,
        public readonly string $eventStartDate,
        public readonly array $contractTerms = []
    ) {}
}
