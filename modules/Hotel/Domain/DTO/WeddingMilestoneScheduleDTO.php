<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\DTO;

final class WeddingMilestoneScheduleDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $milestones
     * @param  array<string, mixed>  $perksSummary
     */
    public function __construct(
        public readonly string $scheduleId,
        public readonly string $weddingContractId,
        public readonly string $coupleNames,
        public readonly int $totalContractValue,
        public readonly int $totalPaidAmount,
        public readonly int $remainingBalance,
        public readonly array $milestones,
        public readonly array $perksSummary,
        public readonly bool $isBridalSuiteUpgraded = false,
        public readonly bool $isDepositFullySettled = false
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schedule_id' => $this->scheduleId,
            'wedding_contract_id' => $this->weddingContractId,
            'couple_names' => $this->coupleNames,
            'total_contract_value' => $this->totalContractValue,
            'total_paid_amount' => $this->totalPaidAmount,
            'remaining_balance' => $this->remainingBalance,
            'milestones' => $this->milestones,
            'perks_summary' => $this->perksSummary,
            'is_bridal_suite_upgraded' => $this->isBridalSuiteUpgraded,
            'is_deposit_fully_settled' => $this->isDepositFullySettled,
        ];
    }
}
