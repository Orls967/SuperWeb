<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

final class WaterfallBreakdownDTO
{
    /**
     * @phpstan-param list<array<string, mixed>> $layerDetails
     */
    public function __construct(
        public readonly int $totalGrossChargesIdr,
        public readonly int $bpjsCoveredAmountIdr,
        public readonly int $privateInsuranceCoveredAmountIdr,
        public readonly int $patientOutOfPocketIdr,
        public readonly int $depositAppliedIdr,
        public readonly int $patientRefundIdr,
        public readonly int $patientFinalPayableIdr,
        public readonly array $layerDetails = [],
        public readonly string $settlementStatus = 'BALANCED',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_gross_charges_idr' => $this->totalGrossChargesIdr,
            'bpjs_covered_amount_idr' => $this->bpjsCoveredAmountIdr,
            'private_insurance_covered_amount_idr' => $this->privateInsuranceCoveredAmountIdr,
            'patient_out_of_pocket_idr' => $this->patientOutOfPocketIdr,
            'deposit_applied_idr' => $this->depositAppliedIdr,
            'patient_refund_idr' => $this->patientRefundIdr,
            'patient_final_payable_idr' => $this->patientFinalPayableIdr,
            'layer_details' => $this->layerDetails,
            'settlement_status' => $this->settlementStatus,
        ];
    }
}
