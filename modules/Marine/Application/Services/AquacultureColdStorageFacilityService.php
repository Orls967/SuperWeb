<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\ProcessingPackaging;

/**
 * Service fasilitas cold storage & validasi HACCP CCP2 pembekuan cepat IQF (Fase 171).
 */
class AquacultureColdStorageFacilityService
{
    // Batas suhu kritis HACCP CCP2 (Critical Control Point) untuk produk beku
    public const MAX_CRITICAL_CORE_TEMP_CELSIUS = -18.0;

    public const OPTIMAL_BLAST_FREEZING_TEMP_CELSIUS = -35.0;

    /**
     * Verifikasi kelolosan titik kendali kritis HACCP CCP2 sebelum lot disimpan di cold storage.
     *
     * @param array{
     *     facility_code: string,
     *     freezing_method: string,
     *     measured_core_temperature_celsius: float,
     *     freezing_duration_minutes: int,
     *     glazing_percentage: float,
     *     haccp_officer_name: string
     * } $ccpInspection
     * @return array{
     *     is_ccp2_compliant: bool,
     *     lot_tracking_number: string,
     *     core_temperature_celsius: float,
     *     thermal_safety_margin: float,
     *     verdict: string,
     *     ccp_log_reference: string
     * }
     */
    public function verifyFreezingHaccpCcp2(int $exportLotId, array $ccpInspection): array
    {
        $lot = AquacultureExportLot::findOrFail($exportLotId);

        $coreTemp = (float) ($ccpInspection['measured_core_temperature_celsius'] ?? -15.0);
        $duration = (int) ($ccpInspection['freezing_duration_minutes'] ?? 0);
        $glazePct = (float) ($ccpInspection['glazing_percentage'] ?? 8.0);

        if ($duration < 30) {
            throw new DomainException('Durasi pembekuan cepat minimal 30 menit dalam terowongan IQF.');
        }

        $isCompliant = ($coreTemp <= self::MAX_CRITICAL_CORE_TEMP_CELSIUS);
        $margin = round(abs(self::MAX_CRITICAL_CORE_TEMP_CELSIUS - $coreTemp), 2);

        $logRef = 'CCP2-'.strtoupper(substr(hash('sha256', $lot->lot_tracking_number.microtime()), 0, 10));

        if ($isCompliant) {
            ProcessingPackaging::updateOrCreate(
                ['export_lot_id' => $lot->id],
                [
                    'packaging_slip_number' => 'SLIP-'.$logRef,
                    'processing_plant_code' => (string) ($ccpInspection['facility_code'] ?? 'ID-PLANT-042'),
                    'haccp_plan_reference' => 'HACCP-REV-2026-A1',
                    'core_temperature_celsius' => $coreTemp,
                    'glazing_water_percentage' => $glazePct,
                    'barcode_gtin14' => '0899'.str_pad((string) $lot->id, 10, '0', STR_PAD_LEFT),
                    'packaging_material' => 'FOOD_GRADE_CORRUGATED_WAXED',
                    'packed_at' => now()->toDateString(),
                    'best_before_date' => now()->addMonths(24)->toDateString(),
                    'sanitation_inspector_name' => (string) ($ccpInspection['haccp_officer_name'] ?? 'Quality Assurance Specialist'),
                ]
            );
        }

        return [
            'is_ccp2_compliant' => $isCompliant,
            'lot_tracking_number' => $lot->lot_tracking_number,
            'core_temperature_celsius' => $coreTemp,
            'thermal_safety_margin' => $margin,
            'verdict' => $isCompliant ? 'HACCP_CCP2_VERIFIED_FROZEN_SOLID' : 'CRITICAL_TEMPERATURE_BREACH_REFREEZE_MANDATORY',
            'ccp_log_reference' => $logRef,
        ];
    }
}
