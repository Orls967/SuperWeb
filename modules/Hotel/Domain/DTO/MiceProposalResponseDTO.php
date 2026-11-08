<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\DTO;

final class MiceProposalResponseDTO
{
    /**
     * @param  array<string, float>  $breakdownRevenue
     * @param  array<string, float>  $breakdownCost
     */
    public function __construct(
        public readonly string $proposalId,
        public readonly string $proposalNumber,
        public readonly string $clientName,
        public readonly int $totalRevenue,
        public readonly int $totalDirectCost,
        public readonly int $grossMarginAmount,
        public readonly float $grossMarginPercent,
        public readonly bool $isMarginGuardApproved,
        public readonly string $expiresAt,
        public readonly array $breakdownRevenue = [],
        public readonly array $breakdownCost = [],
        public readonly string $status = 'draft'
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'proposal_id' => $this->proposalId,
            'proposal_number' => $this->proposalNumber,
            'client_name' => $this->clientName,
            'total_revenue' => $this->totalRevenue,
            'total_direct_cost' => $this->totalDirectCost,
            'gross_margin_amount' => $this->grossMarginAmount,
            'gross_margin_percent' => $this->grossMarginPercent,
            'is_margin_guard_approved' => $this->isMarginGuardApproved,
            'expires_at' => $this->expiresAt,
            'breakdown_revenue' => $this->breakdownRevenue,
            'breakdown_cost' => $this->breakdownCost,
            'status' => $this->status,
        ];
    }
}
