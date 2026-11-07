<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningBlastClearanceRun;
use Modules\Mining\Domain\Models\MiningBlastFragmentationEval;
use Modules\Shared\Domain\Algorithms\Blasting\BlastGeometry;
use Modules\Shared\Domain\Algorithms\Blasting\ExplosiveProperty;
use Modules\Shared\Domain\Algorithms\Blasting\KcoBlastingFragmentationModel;

/**
 * Domain Action: Evaluasi Fragmentasi Batuan Muckpile Peledakan (Kuz-Ram / KCO Model).
 * Menjalankan algoritma analitik fragmentasi M8 untuk mengevaluasi ukuran ayakan d50/d80,
 * persentase bongkah besar (oversize boulder), dan fines debu mineral.
 */
class EvaluateMuckpileKcoFragmentationAction
{
    public function __construct(
        private readonly KcoBlastingFragmentationModel $kcoModel
    ) {}

    /**
     * @param  array{
     *     blast_clearance_run_id: string,
     *     eval_code: string,
     *     rock_factor_a: float,
     *     burden_meters: float,
     *     spacing_meters: float,
     *     hole_diameter_mm: float,
     *     bench_height_meters: float,
     *     charge_length_meters: float,
     *     explosive_rws: float,
     *     explosive_density: float,
     *     target_crusher_topsize_mm?: float,
     *     evaluator_name?: string
     * } $data
     */
    public function execute(array $data): MiningBlastFragmentationEval
    {
        $this->validateData($data);

        $run = MiningBlastClearanceRun::query()->findOrFail($data['blast_clearance_run_id']);

        $geometry = new BlastGeometry(
            burdenM: (float) $data['burden_meters'],
            spacingM: (float) $data['spacing_meters'],
            holeDiameterMm: (float) $data['hole_diameter_mm'],
            benchHeightM: (float) $data['bench_height_meters'],
            holeDepthM: (float) ($data['bench_height_meters'] + 1.0),
            stemmingLengthM: (float) max(1.0, ($data['bench_height_meters'] - ($data['charge_length_meters'] ?? 8.0))),
            drillingAccuracyDeviationM: 0.1
        );

        $explosive = new ExplosiveProperty(
            explosiveType: 'Emulsion-ANFO Blend 70/30',
            densityGPerCm3: (float) ($data['explosive_density'] ?? 1.15),
            relativeWeightStrengthRws: (float) ($data['explosive_rws'] ?? 105.0),
            velocityOfDetonationMPerS: 5200.0
        );

        $topsizeTargetMm = (float) ($data['target_crusher_topsize_mm'] ?? 800.0);
        $rockA = (float) ($data['rock_factor_a'] ?? 10.0);
        $chargeKg = (float) ($data['charge_weight_kg_per_hole'] ?? 120.0);

        $result = $this->kcoModel->evaluate(
            geometry: $geometry,
            explosive: $explosive,
            rockMassFactorA: $rockA,
            chargeKgPerHole: $chargeKg,
            oversizeThresholdMm: $topsizeTargetMm
        );

        $eval = new MiningBlastFragmentationEval;
        $eval->fill([
            'eval_code' => strtoupper(trim($data['eval_code'])),
            'blast_clearance_run_id' => $run->id,
            'mean_fragment_size_d50_mm' => $result->meanFragmentSizeX50Mm,
            'median_size_x50_mm' => $result->meanFragmentSizeX50Mm,
            'passing_80_percent_d80_mm' => round($result->meanFragmentSizeX50Mm * 1.85, 2),
            'boulder_oversize_percent' => $result->oversizePercent,
            'oversize_percent' => $result->oversizePercent,
            'fines_undersize_percent' => $result->finesPercent,
            'fines_percent' => $result->finesPercent,
            'uniformity_index_n' => $result->uniformityIndexN,
            'characteristic_size_xc_cm' => $result->characteristicSizeXcPct,
            'powder_factor_kg_m3' => $result->powderFactorKgPerM3,
            'powder_factor_adjustment_kg_m3' => $result->recommendedPowderFactorAdjustmentKgPerM3,
            'burden_adjustment_m' => $result->recommendedBurdenAdjustmentM,
            'spacing_adjustment_m' => $result->recommendedSpacingAdjustmentM,
            'secondary_breaking_needed' => $result->isSecondaryBreakingRequired,
            'hmac_signature' => $result->hmacSignature,
            'status' => $result->isSecondaryBreakingRequired ? 'EXCESSIVE_BOULDERS' : 'OPTIMAL',
            'metadata' => [
                'target_crusher_topsize_mm' => $topsizeTargetMm,
                'evaluator' => $data['evaluator_name'] ?? 'Mine Drill & Blast Engineer',
                'evaluated_at' => now()->toIso8601String(),
            ],
        ]);

        $eval->save();

        return $eval;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateData(array $data): void
    {
        if (empty($data['blast_clearance_run_id'])) {
            throw new InvalidArgumentException('Blast clearance run ID harus diisi.');
        }

        if (empty($data['eval_code'])) {
            throw new InvalidArgumentException('Evaluation code tidak boleh kosong.');
        }

        if (($data['burden_meters'] ?? 0) <= 0 || ($data['spacing_meters'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Burden dan spacing harus lebih besar dari 0 meter.');
        }
    }
}
