<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use Modules\Marine\Contracts\VerifiedHarvestReader;
use Modules\Marine\Domain\Models\AquacultureExportLot;

/**
 * Implementasi Kontrak G7-K28: VerifiedHarvestReader.
 */
class VerifiedHarvestReaderService implements VerifiedHarvestReader
{
    /**
     * Dapatkan detail panen terverifikasi berdasarkan nomor lot ekspor.
     *
     * @return array{
     *     lot_number: string,
     *     species: string,
     *     harvest_date: string,
     *     pond_identifier: string,
     *     net_weight_kg: float,
     *     merkle_root_hash: string,
     *     is_assay_passed: bool,
     *     health_certificate_grade: string,
     *     origin_hatchery: string,
     *     fcr_achieved: float
     * }|null
     */
    public function getVerifiedHarvestLot(string $lotNumber): ?array
    {
        $exportLot = AquacultureExportLot::with(['harvestOperation.batch.hatchery', 'laboratoryAssays', 'traceabilityNodes'])
            ->where('lot_tracking_number', $lotNumber)
            ->first();

        if (! $exportLot) {
            return null;
        }

        $harvest = $exportLot->harvestOperation;
        $batch = $harvest?->batch;
        $hatchery = $batch?->hatchery;
        $latestAssay = $exportLot->laboratoryAssays()->latest('id')->first();
        $firstNode = $exportLot->traceabilityNodes()->first();

        $isPassed = $latestAssay ? ($latestAssay->overall_compliance_verdict === 'PASSED_FOR_EXPORT') : false;

        return [
            'lot_number' => $exportLot->lot_tracking_number,
            'species' => $batch ? $batch->species_scientific_name : 'Litopenaeus vannamei',
            'harvest_date' => $harvest ? (string) $harvest->harvested_at : '',
            'pond_identifier' => $batch ? 'POND-'.$batch->pond_id : '',
            'net_weight_kg' => (float) $exportLot->net_lot_weight_kg,
            'merkle_root_hash' => $firstNode ? (string) $firstNode->merkle_root_hash : '',
            'is_assay_passed' => $isPassed,
            'health_certificate_grade' => $exportLot->commercial_grade,
            'origin_hatchery' => $hatchery ? $hatchery->name : 'Unknown',
            'fcr_achieved' => $harvest ? (float) $harvest->final_fcr : 1.25,
        ];
    }

    /**
     * Periksa apakah lot ekspor memenuhi kriteria pelepasan escrow petani / nelayan tambak.
     */
    public function isEligibleForEscrowRelease(string $lotNumber): bool
    {
        $lot = $this->getVerifiedHarvestLot($lotNumber);
        if (! $lot) {
            return false;
        }

        return $lot['is_assay_passed'];
    }
}
