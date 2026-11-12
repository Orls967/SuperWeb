<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final class BudgetCommitmentRequest
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $budgetId,
        public readonly string $commitmentCode,
        public readonly string $sourceModule,
        public readonly string $referenceNumber,
        public readonly int $amountMinor,
        public readonly string $reason = '',
        public readonly array $payload = []
    ) {}
}
