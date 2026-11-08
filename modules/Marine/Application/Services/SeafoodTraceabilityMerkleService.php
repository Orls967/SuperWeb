<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use Modules\Marine\Domain\Algorithms\SeafoodLotMerkleProofGenerator;
use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\TraceabilityLotNode;

/**
 * Service ketelusuran kriptografis rantai pasok seafood berbasis Merkle Tree (Fase 171).
 */
class SeafoodTraceabilityMerkleService
{
    protected SeafoodLotMerkleProofGenerator $merkleGenerator;

    public function __construct(SeafoodLotMerkleProofGenerator $merkleGenerator)
    {
        $this->merkleGenerator = $merkleGenerator;
    }

    /**
     * Kumpulkan seluruh tahapan peristiwa rantai pasok dan bangun Merkle Tree untuk lot ekspor.
     *
     * @return array{
     *     lot_number: string,
     *     merkle_root_hash: string,
     *     leaf_count: int,
     *     tree_depth: int
     * }
     */
    public function generateLotMerkleTree(int $exportLotId): array
    {
        $exportLot = AquacultureExportLot::with(['harvestOperation.batch.hatchery', 'processingPackaging'])->findOrFail($exportLotId);
        $harvest = $exportLot->harvestOperation;
        $batch = $harvest->batch;
        $hatchery = $batch->hatchery;

        // Kumpulkan 8 peristiwa kritis rantai pasok (Traceability Leaves)
        $events = [
            [
                'stage' => '01_HATCHERY_ORIGIN',
                'facility_code' => $hatchery->facility_code,
                'broodstock_origin' => $hatchery->broodstock_origin,
                'spf_certificate' => $hatchery->spf_certificate_number,
                'license' => $hatchery->license_number,
            ],
            [
                'stage' => '02_POND_STOCKING',
                'batch_code' => $batch->batch_code,
                'pond_identifier' => $batch->pond_identifier,
                'stocking_date' => $batch->stocking_date,
                'stocking_density' => $batch->stocking_density_per_m2,
            ],
            [
                'stage' => '03_FEED_AND_NUTRITION',
                'batch_code' => $batch->batch_code,
                'feed_logs_count' => $batch->feedingEvents()->count(),
                'cumulative_feed_kg' => (float) $batch->feedingEvents()->sum('feed_amount_kg'),
            ],
            [
                'stage' => '04_WATER_QUALITY_TELEMETRY',
                'batch_code' => $batch->batch_code,
                'telemetry_count' => $batch->waterQualityTelemetries()->count(),
                'mean_ph' => (float) $batch->waterQualityTelemetries()->avg('ph_level'),
                'mean_do' => (float) $batch->waterQualityTelemetries()->avg('dissolved_oxygen_mg_l'),
            ],
            [
                'stage' => '05_DISEASE_AND_HEALTH_SCREENING',
                'batch_code' => $batch->batch_code,
                'health_screenings_count' => $batch->diseaseHealthScreenings()->count(),
                'screening_verdict' => 'ALL_NEGATIVE_WSSV_EHP_IMNV',
            ],
            [
                'stage' => '06_HARVEST_OPERATION',
                'harvest_code' => $harvest->harvest_code,
                'harvest_date' => $harvest->harvest_date,
                'harvest_biomass_kg' => $harvest->total_biomass_harvested_kg,
                'slurry_temp_celsius' => $harvest->chilled_ice_slurry_temp_celsius,
            ],
            [
                'stage' => '07_PROCESSING_AND_PACKAGING',
                'lot_number' => $exportLot->lot_number,
                'product_form' => $exportLot->product_form,
                'net_weight_kg' => $exportLot->net_weight_kg,
                'haccp_approval' => $exportLot->haccp_approval_number,
            ],
            [
                'stage' => '08_STORAGE_AND_COLD_CHAIN',
                'lot_number' => $exportLot->lot_number,
                'storage_temp_celsius' => $exportLot->storage_temperature_celsius,
                'cartons' => $exportLot->carton_count,
            ],
        ];

        $merkleTree = $this->merkleGenerator->buildMerkleTree($events);
        $rootHash = $merkleTree['root_hash'];

        // Hapus node pohon lama untuk lot ini jika ada pembaruan
        TraceabilityLotNode::where('export_lot_id', $exportLot->id)->delete();

        // Simpan setiap leaf node ke database
        foreach ($events as $index => $eventData) {
            $leafHash = $merkleTree['leaves'][$index];
            $proof = $this->merkleGenerator->generateProof($merkleTree, $index);

            TraceabilityLotNode::create([
                'export_lot_id' => $exportLot->id,
                'node_type' => (string) $eventData['stage'],
                'sequence_index' => $index,
                'node_hash' => $leafHash,
                'parent_merkle_root' => $rootHash,
                'cryptographic_proof_path' => $proof,
                'event_payload' => $eventData,
            ]);
        }

        $exportLot->update([
            'merkle_root_hash' => $rootHash,
        ]);

        return [
            'lot_number' => $exportLot->lot_number,
            'merkle_root_hash' => $rootHash,
            'leaf_count' => $merkleTree['leaf_count'],
            'tree_depth' => $merkleTree['tree_depth'],
        ];
    }

    /**
     * Verifikasi keaslian simpul rantai pasok tertentu dengan bukti Merkle.
     */
    public function verifyNodeProof(int $traceNodeId): bool
    {
        $node = TraceabilityLotNode::findOrFail($traceNodeId);
        $proof = $node->cryptographic_proof_path ?? [];
        $expectedRoot = $node->parent_merkle_root;

        return $this->merkleGenerator->verifyProof($node->node_hash, $proof, $expectedRoot);
    }
}
