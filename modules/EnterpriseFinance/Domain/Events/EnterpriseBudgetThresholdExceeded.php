<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Events;

final class EnterpriseBudgetThresholdExceeded
{
    public const EVENT_NAME = 'enterprise.budget.threshold_exceeded.v1';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly int $budgetId,
        public readonly string $budgetCode,
        public readonly string $entityCode,
        public readonly int $allocatedAmountMinor,
        public readonly int $remainingAvailableMinor,
        public readonly int $thresholdPercent,
        public readonly array $metadata = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'budget_id' => $this->budgetId,
            'budget_code' => $this->budgetCode,
            'entity_code' => $this->entityCode,
            'allocated_amount_minor' => $this->allocatedAmountMinor,
            'remaining_available_minor' => $this->remainingAvailableMinor,
            'threshold_percent' => $this->thresholdPercent,
            'metadata' => $this->metadata,
        ];
    }
}
