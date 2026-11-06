<?php

declare(strict_types=1);

namespace Modules\Egy\Application\DTOs;

/**
 * Rekapitulasi Pelaporan Triwulanan Bea Pabean CBAM Ekspor (EU 2023/956).
 */
final class CbamQuarterlyReportDTO
{
    /**
     * @param  list<array<string, mixed>>  $declarations
     */
    public function __construct(
        public readonly string $reportingQuarter,
        public readonly int $totalShipmentsCount,
        public readonly int $totalExportTonnageKg,
        public readonly int $totalGrossEmissionsKgCo2e,
        public readonly int $totalNetPayableEmissionsKgCo2e,
        public readonly int $totalLiabilityEurMinor,
        public readonly int $totalLiabilityIdr,
        public readonly array $declarations,
        public readonly string $quarterlyCertificateHash,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reporting_quarter' => $this->reportingQuarter,
            'total_shipments_count' => $this->totalShipmentsCount,
            'total_export_tonnage_kg' => $this->totalExportTonnageKg,
            'total_gross_emissions_kg_co2e' => $this->totalGrossEmissionsKgCo2e,
            'total_net_payable_emissions_kg_co2e' => $this->totalNetPayableEmissionsKgCo2e,
            'total_liability_eur_minor' => $this->totalLiabilityEurMinor,
            'total_liability_idr' => $this->totalLiabilityIdr,
            'declarations' => $this->declarations,
            'quarterly_certificate_hash' => $this->quarterlyCertificateHash,
        ];
    }
}
