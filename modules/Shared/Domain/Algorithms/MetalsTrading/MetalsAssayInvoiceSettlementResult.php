<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\MetalsTrading;

final class MetalsAssayInvoiceSettlementResult
{
    public function __construct(
        public readonly float $agreedFinalNiGradePct,
        public readonly bool $isDisputeTriggered,
        public readonly bool $isUmpireResolved,
        public readonly string $umpireFeeBearingParty,
        public readonly int $provisionalInvoiceIdr,
        public readonly int $finalPayableValueIdr,
        public readonly int $settlementDifferentialIdr,
        public readonly int $sellerUmpireCostAllocationIdr,
        public readonly int $buyerUmpireCostAllocationIdr,
        public readonly string $verificationHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'agreed_final_ni_grade_pct' => $this->agreedFinalNiGradePct,
            'is_dispute_triggered' => $this->isDisputeTriggered,
            'is_umpire_resolved' => $this->isUmpireResolved,
            'umpire_fee_bearing_party' => $this->umpireFeeBearingParty,
            'provisional_invoice_idr' => $this->provisionalInvoiceIdr,
            'final_payable_value_idr' => $this->finalPayableValueIdr,
            'settlement_differential_idr' => $this->settlementDifferentialIdr,
            'seller_umpire_cost_allocation_idr' => $this->sellerUmpireCostAllocationIdr,
            'buyer_umpire_cost_allocation_idr' => $this->buyerUmpireCostAllocationIdr,
            'verification_hash' => $this->verificationHash,
        ];
    }
}
