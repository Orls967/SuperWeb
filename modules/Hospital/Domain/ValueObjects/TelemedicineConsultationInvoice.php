<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\ValueObjects;

final class TelemedicineConsultationInvoice
{
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly string $consultCode,
        public readonly int $consultationFeeIdr,
        public readonly int $prescriptionMedicinesIdr,
        public readonly int $deliveryFreightFeeIdr,
        public readonly int $insuranceCoverageIdr,
        public readonly int $patientPayableIdr,
        public readonly string $settlementStatus = 'UNPAID'
    ) {}

    public function grossTotalIdr(): int
    {
        return $this->consultationFeeIdr + $this->prescriptionMedicinesIdr + $this->deliveryFreightFeeIdr;
    }

    public function netPatientShareIdr(): int
    {
        return max(0, $this->grossTotalIdr() - $this->insuranceCoverageIdr);
    }

    public function toArray(): array
    {
        return [
            'invoice_number' => $this->invoiceNumber,
            'consult_code' => $this->consultCode,
            'consultation_fee_idr' => $this->consultationFeeIdr,
            'prescription_medicines_idr' => $this->prescriptionMedicinesIdr,
            'delivery_freight_fee_idr' => $this->deliveryFreightFeeIdr,
            'gross_total_idr' => $this->grossTotalIdr(),
            'insurance_coverage_idr' => $this->insuranceCoverageIdr,
            'patient_payable_idr' => $this->patientPayableIdr,
            'settlement_status' => $this->settlementStatus,
        ];
    }
}
