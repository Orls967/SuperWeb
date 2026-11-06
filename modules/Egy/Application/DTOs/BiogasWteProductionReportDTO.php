<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * Data Transfer Object untuk Laporan Produksi Biogas dan Waste-to-Energy (WTE).
 */
final class BiogasWteProductionReportDTO
{
    public function __construct(
        public readonly int $facilityId,
        public readonly string $facilityName,
        public readonly string $facilityCode,
        public readonly int $totalWasteReceivedKg,
        public readonly int $totalBiogasProducedM3,
        public readonly int $totalElectricityGeneratedKwh,
        public readonly int $totalTippingFeeRevenueIdr,
        public readonly int $totalAvoidedEmissionsCo2eKg,
        public readonly int $intakeBatchesCount,
        public readonly string $status,
        public readonly string $verificationHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'facility_id' => $this->facilityId,
            'facility_name' => $this->facilityName,
            'facility_code' => $this->facilityCode,
            'total_waste_received_kg' => $this->totalWasteReceivedKg,
            'total_biogas_produced_m3' => $this->totalBiogasProducedM3,
            'total_electricity_generated_kwh' => $this->totalElectricityGeneratedKwh,
            'total_tipping_fee_revenue_idr' => $this->totalTippingFeeRevenueIdr,
            'total_avoided_emissions_co2e_kg' => $this->totalAvoidedEmissionsCo2eKg,
            'intake_batches_count' => $this->intakeBatchesCount,
            'status' => $this->status,
            'verification_hash' => $this->verificationHash,
        ];
    }
}
