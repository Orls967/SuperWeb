<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\AquacultureExportLot;

/**
 * Service penerbitan dokumen ekspor internasional seafood dan integrasi logistik multimoda (Fase 171 Sub-item 171.2).
 */
class ExportDocumentationService
{
    /**
     * Terbitkan Health Certificate karantina resmi dari Balai Karantina Ikan KKP.
     *
     * @return array{
     *     certificate_number: string,
     *     lot_tracking_number: string,
     *     species: string,
     *     net_weight_kg: float,
     *     accredited_lab: string,
     *     export_permit_status: string,
     *     issued_at: string
     * }
     */
    public function issueHealthCertificate(int $exportLotId, string $inspectorName): array
    {
        $lot = AquacultureExportLot::with(['harvestOperation.batch', 'laboratoryAssays'])->findOrFail($exportLotId);
        $latestAssay = $lot->laboratoryAssays()->latest('id')->first();

        if (! $latestAssay || $latestAssay->overall_compliance_verdict !== 'PASSED_FOR_EXPORT') {
            throw new DomainException('Penerbitan Health Certificate ditolak: Uji laboratorium SPS belum lolos.');
        }

        $hcNumber = 'HC-KKP-ID-'.date('Y').'-'.strtoupper(substr(hash('sha256', $lot->lot_tracking_number.microtime()), 0, 8));

        return [
            'certificate_number' => $hcNumber,
            'lot_tracking_number' => $lot->lot_tracking_number,
            'species' => $lot->harvestOperation->batch->species_scientific_name ?? 'Litopenaeus vannamei',
            'net_weight_kg' => (float) $lot->net_lot_weight_kg,
            'accredited_lab' => $latestAssay->accredited_laboratory_name,
            'export_permit_status' => 'OFFICIALLY_CLEARED_FOR_EXPORT',
            'issued_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Terbitkan Certificate of Origin (Surat Keterangan Asal / SKA).
     *
     * @param  string  $formType  'FORM_D'|'FORM_AK'|'FORM_IJEPA'|'FORM_GENERAL'
     * @return array{
     *     coo_number: string,
     *     form_type: string,
     *     origin_country: string,
     *     consignor: string,
     *     consignee_country: string,
     *     origin_criterion: string,
     *     is_valid: bool
     * }
     */
    public function generateCertificateOfOrigin(
        int $exportLotId,
        string $formType,
        string $consigneeCountry
    ): array {
        $lot = AquacultureExportLot::with('harvestOperation.batch.hatchery')->findOrFail($exportLotId);

        $cooNumber = 'COO-'.strtoupper($formType).'-'.strtoupper(substr(hash('sha256', $lot->lot_tracking_number.microtime()), 0, 8));

        return [
            'coo_number' => $cooNumber,
            'form_type' => strtoupper($formType),
            'origin_country' => 'INDONESIA',
            'consignor' => 'PT Nusantara Aquaculture Exporter',
            'consignee_country' => strtoupper($consigneeCountry),
            'origin_criterion' => 'WHOLLY_PRODUCED_OR_OBTAINED (WO)',
            'is_valid' => true,
        ];
    }

    /**
     * Simulasi pembuatan dokumen Pemberitahuan Ekspor Barang (PEB) Bea Cukai.
     *
     * @return array{
     *     peb_registration_number: string,
     *     hs_code: string,
     *     customs_office_code: string,
     *     fob_value_minor_cents: int,
     *     currency: string,
     *     green_channel_cleared: bool
     * }
     */
    public function submitCustomsExportDeclaration(
        int $exportLotId,
        string $hsCode = '0306.17.00', // HS Code Udang Beku Air Payau
        string $customsOffice = '070100' // KPU Bea Cukai Tanjung Perak Surabaya
    ): array {
        $lot = AquacultureExportLot::findOrFail($exportLotId);

        $pebNumber = 'PEB-'.date('Ymd').'-'.strtoupper(substr(hash('sha256', $lot->lot_tracking_number), 0, 8));

        return [
            'peb_registration_number' => $pebNumber,
            'hs_code' => $hsCode,
            'customs_office_code' => $customsOffice,
            'fob_value_minor_cents' => (int) $lot->estimated_export_value_minor_cents,
            'currency' => 'USD',
            'green_channel_cleared' => true,
        ];
    }
}
