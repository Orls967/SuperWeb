<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Queries;

use Modules\Marine\Domain\Models\AquacultureExportLot;

/**
 * Query service pencarian dan silsilah lengkap rantai pasok akuakultur ekspor (Fase 171).
 */
class AquacultureTraceabilitySearchQuery
{
    /**
     * Cari lot ekspor berdasarkan nomor pelacakan, rentang tanggal panen, atau mutu.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function searchLots(array $filters): array
    {
        $query = AquacultureExportLot::with([
            'harvestOperation.batch.hatchery',
            'processingPackaging',
            'laboratoryAssays',
            'traceabilityNodes',
            'exportShipments',
        ]);

        if (! empty($filters['lot_tracking_number'])) {
            $query->where('lot_tracking_number', 'like', '%'.$filters['lot_tracking_number'].'%');
        }

        if (! empty($filters['commercial_grade'])) {
            $query->where('commercial_grade', $filters['commercial_grade']);
        }

        if (! empty($filters['product_form'])) {
            $query->where('product_form', $filters['product_form']);
        }

        $lots = $query->latest('id')->take(50)->get();

        $results = [];
        foreach ($lots as $lot) {
            $harvest = $lot->harvestOperation;
            $batch = $harvest?->batch;
            $hatchery = $batch?->hatchery;
            $latestAssay = $lot->laboratoryAssays->first();
            $firstNode = $lot->traceabilityNodes->first();
            $shipment = $lot->exportShipments->first();

            $results[] = [
                'lot_id' => $lot->id,
                'lot_tracking_number' => $lot->lot_tracking_number,
                'product_form' => $lot->product_form,
                'commercial_grade' => $lot->commercial_grade,
                'net_lot_weight_kg' => (float) $lot->net_lot_weight_kg,
                'pack_count_cartons' => (int) $lot->pack_count_cartons,
                'hatchery_name' => $hatchery ? $hatchery->name : 'N/A',
                'spf_certificate' => $hatchery ? $hatchery->spf_certificate_number : 'N/A',
                'harvest_date' => $harvest ? (string) $harvest->harvested_at : 'N/A',
                'fcr' => $harvest ? (float) $harvest->final_fcr : 0.0,
                'merkle_root_hash' => $firstNode ? $firstNode->merkle_root_hash : 'UNANCHORED',
                'sps_verdict' => $latestAssay ? $latestAssay->overall_compliance_verdict : 'PENDING',
                'container_number' => $shipment ? $shipment->container_iso_number : 'UNALLOCATED',
                'ocean_bl' => $shipment ? $shipment->ocean_bill_of_lading : 'UNISSUED',
            ];
        }

        return $results;
    }
}
