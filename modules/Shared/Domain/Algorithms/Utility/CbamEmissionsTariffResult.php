<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Utility;

/**
 * Nilai hasil komputasi tarif bea perbatasan karbon CBAM Uni Eropa.
 */
final class CbamEmissionsTariffResult
{
    public function __construct(
        public readonly int $productTonnageKg,
        public readonly int $specificEmissionFactorKgCo2ePerTon,
        public readonly int $euBenchmarkFactorKgCo2ePerTon,
        public readonly int $grossEmbeddedEmissionsKgCo2e,
        public readonly int $netPayableEmissionsKgCo2e,
        public readonly int $freeAllowanceDeductionKgCo2e,
        public readonly int $liabilityEurMinor,
        public readonly int $liabilityIdr,
        public readonly string $certificateHash,
        public readonly string $canonicalPayload,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_tonnage_kg' => $this->productTonnageKg,
            'specific_emission_factor_kg_co2e_per_ton' => $this->specificEmissionFactorKgCo2ePerTon,
            'eu_benchmark_factor_kg_co2e_per_ton' => $this->euBenchmarkFactorKgCo2ePerTon,
            'gross_embedded_emissions_kg_co2e' => $this->grossEmbeddedEmissionsKgCo2e,
            'net_payable_emissions_kg_co2e' => $this->netPayableEmissionsKgCo2e,
            'free_allowance_deduction_kg_co2e' => $this->freeAllowanceDeductionKgCo2e,
            'liability_eur_minor' => $this->liabilityEurMinor,
            'liability_idr' => $this->liabilityIdr,
            'certificate_hash' => $this->certificateHash,
            'canonical_payload' => $this->canonicalPayload,
        ];
    }
}
