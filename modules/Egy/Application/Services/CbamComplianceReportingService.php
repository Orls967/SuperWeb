<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Modules\Egy\Application\DTOs\CbamQuarterlyReportDTO;
use Modules\Egy\Domain\Models\CbamDeclaration;

/**
 * Layanan Pelaporan Kepatuhan Bea Pabean CBAM Ekspor (EU 2023/956).
 * Menyusun rekapitulasi pelaporan triwulanan ekspor komoditas padat karbon ke Uni Eropa.
 */
final class CbamComplianceReportingService
{
    /**
     * Hasilkan rekapitulasi laporan triwulan CBAM.
     */
    public function generateQuarterlyReport(string $reportingQuarter): CbamQuarterlyReportDTO
    {
        $declarations = CbamDeclaration::where('status', 'declared')
            ->orderBy('created_at', 'asc')
            ->get();

        $totalShipments = $declarations->count();
        $totalExportTonnageKg = 0;
        $totalGrossEmissionsKg = 0;
        $totalNetPayableEmissionsKg = 0;
        $totalLiabilityEurMinor = 0;
        $totalLiabilityIdr = 0;
        $declarationRows = [];

        foreach ($declarations as $dec) {
            $totalExportTonnageKg += (int) $dec->product_tonnage_kg;
            $totalGrossEmissionsKg += (int) $dec->gross_embedded_emissions_kg_co2e;
            $totalNetPayableEmissionsKg += (int) $dec->net_payable_emissions_kg_co2e;
            $totalLiabilityEurMinor += (int) $dec->total_cbam_liability_eur_minor;
            $totalLiabilityIdr += (int) $dec->total_cbam_liability_idr;

            $declarationRows[] = [
                'declaration_number' => $dec->declaration_number,
                'export_contract' => $dec->export_contract_number,
                'container_number' => $dec->container_number,
                'hs_code' => $dec->hs_code,
                'tonnage_kg' => $dec->product_tonnage_kg,
                'net_emissions_kg' => $dec->net_payable_emissions_kg_co2e,
                'liability_eur_minor' => $dec->total_cbam_liability_eur_minor,
                'liability_idr' => $dec->total_cbam_liability_idr,
                'certificate_hash' => $dec->certificate_hash,
            ];
        }

        $canonicalReportPayload = sprintf(
            'CBAM-QUARTERLY|Q:%s|SHIPMENTS:%d|TON:%d|NET_KG:%d|EUR:%d|IDR:%d',
            $reportingQuarter,
            $totalShipments,
            $totalExportTonnageKg,
            $totalNetPayableEmissionsKg,
            $totalLiabilityEurMinor,
            $totalLiabilityIdr
        );
        $quarterlyHash = hash('sha256', $canonicalReportPayload);

        return new CbamQuarterlyReportDTO(
            reportingQuarter: $reportingQuarter,
            totalShipmentsCount: $totalShipments,
            totalExportTonnageKg: $totalExportTonnageKg,
            totalGrossEmissionsKgCo2e: $totalGrossEmissionsKg,
            totalNetPayableEmissionsKgCo2e: $totalNetPayableEmissionsKg,
            totalLiabilityEurMinor: $totalLiabilityEurMinor,
            totalLiabilityIdr: $totalLiabilityIdr,
            declarations: $declarationRows,
            quarterlyCertificateHash: $quarterlyHash
        );
    }
}
