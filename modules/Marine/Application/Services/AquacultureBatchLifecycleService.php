<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\Models\AquacultureHatchery;
use Modules\Marine\Domain\StateMachines\AquacultureBatchStateMachine;

/**
 * Service siklus hidup tambak udang dan KJA ikan (Fase 171).
 */
class AquacultureBatchLifecycleService
{
    protected AquacultureBatchStateMachine $stateMachine;

    public function __construct(AquacultureBatchStateMachine $stateMachine)
    {
        $this->stateMachine = $stateMachine;
    }

    /**
     * Daftarkan batch tambak baru dari benur/bibit hatchery tersertifikasi SPF/SPR.
     *
     * @param  array<string, mixed>  $stockingData
     */
    public function stockPondBatch(int $hatcheryId, array $stockingData): AquacultureBatch
    {
        $hatchery = AquacultureHatchery::findOrFail($hatcheryId);

        if (! $hatchery->is_active) {
            throw new DomainException("Hatchery {$hatchery->facility_name} sedang non-aktif.");
        }

        $stockingCount = (int) ($stockingData['stocking_count'] ?? 0);
        if ($stockingCount <= 0) {
            throw new DomainException('Jumlah tebar benur/bibit harus lebih besar dari 0.');
        }

        $pondAreaHa = (float) ($stockingData['pond_area_hectares'] ?? 0.5);
        $stockingDensity = round($stockingCount / ($pondAreaHa * 10000), 2); // per m²

        $batchCode = 'BATCH-'.strtoupper(substr(hash('sha256', $hatchery->facility_code.microtime()), 0, 10));

        return AquacultureBatch::create([
            'hatchery_id' => $hatchery->id,
            'batch_code' => $batchCode,
            'pond_identifier' => (string) ($stockingData['pond_identifier'] ?? 'TAMBAK-A1'),
            'species' => (string) ($stockingData['species'] ?? 'Litopenaeus vannamei'),
            'stocking_date' => (string) ($stockingData['stocking_date'] ?? now()->toDateString()),
            'stocking_count' => $stockingCount,
            'current_population' => $stockingCount,
            'pond_area_hectares' => $pondAreaHa,
            'stocking_density_per_m2' => $stockingDensity,
            'target_harvest_biomass_kg' => (float) ($stockingData['target_harvest_biomass_kg'] ?? 5000.0),
            'current_estimated_biomass_kg' => (float) ($stockingData['initial_biomass_kg'] ?? 10.0),
            'status' => AquacultureBatchStateMachine::STATUS_HATCHERY_STOCKED,
            'notes' => (string) ($stockingData['notes'] ?? 'Batch tebar baru'),
        ]);
    }

    /**
     * Transisikan status batch tambak.
     */
    public function advanceBatchStatus(int $batchId, string $targetStatus): AquacultureBatch
    {
        $batch = AquacultureBatch::findOrFail($batchId);
        $newStatus = $this->stateMachine->transition($batch->status, $targetStatus);

        $batch->update([
            'status' => $newStatus,
        ]);

        return $batch;
    }

    /**
     * Catat sampling biomassa berkala (Mean Body Weight / MBW & survival rate).
     */
    public function recordSampling(int $batchId, float $meanBodyWeightGrams, float $survivalRatePercentage): AquacultureBatch
    {
        $batch = AquacultureBatch::findOrFail($batchId);

        if ($meanBodyWeightGrams <= 0.0 || $survivalRatePercentage <= 0.0) {
            throw new DomainException('Hasil sampling bobot dan survival rate harus positif.');
        }

        $estimatedPopulation = (int) round($batch->stocking_count * ($survivalRatePercentage / 100.0));
        $estimatedBiomassKg = round(($estimatedPopulation * $meanBodyWeightGrams) / 1000.0, 2);

        $batch->update([
            'current_population' => $estimatedPopulation,
            'current_estimated_biomass_kg' => $estimatedBiomassKg,
        ]);

        return $batch;
    }
}
