<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object immutable saldo anggaran kegagalan (Error Budget) dan laju pembakaran (Burn Rate).
 */
final class ErrorBudgetBalance
{
    public function __construct(
        public readonly string $serviceName,
        public readonly int $targetSliBps,
        public readonly int $totalBudgetMins,
        public readonly float $consumedMins,
        public readonly float $burnRate1h,
        public readonly float $burnRate6h
    ) {
        if ($this->targetSliBps < 0 || $this->targetSliBps > 10000) {
            throw new InvalidArgumentException("Target SLI basis poin harus berada di rentang 0-10000: {$this->targetSliBps}");
        }
    }

    public function remainingMins(): float
    {
        return max(0.0, $this->totalBudgetMins - $this->consumedMins);
    }

    public function remainingPercentage(): float
    {
        if ($this->totalBudgetMins <= 0) {
            return 0.0;
        }

        return round(($this->remainingMins() / $this->totalBudgetMins) * 100.0, 2);
    }

    public function isExhausted(): bool
    {
        return $this->remainingMins() <= 0.0;
    }

    public function isFastBurning(): bool
    {
        return $this->burnRate1h >= 14.4 || $this->burnRate6h >= 6.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service_name' => $this->serviceName,
            'target_sli_bps' => $this->targetSliBps,
            'total_budget_mins' => $this->totalBudgetMins,
            'consumed_mins' => $this->consumedMins,
            'remaining_mins' => $this->remainingMins(),
            'remaining_percent' => $this->remainingPercentage(),
            'burn_rate_1h' => $this->burnRate1h,
            'burn_rate_6h' => $this->burnRate6h,
            'is_exhausted' => $this->isExhausted(),
            'is_fast_burning' => $this->isFastBurning(),
        ];
    }
}
