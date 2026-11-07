<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Mining\Domain\Algorithms\InverseDistanceWeightingInterpolator;
use Modules\Mining\Domain\Models\MiningBlockModelVoxel;
use Modules\Mining\Domain\Models\MiningBoreholeAssay;
use Modules\Mining\Domain\Models\MiningPit;
use Modules\Shared\Domain\Algorithms\Geo\OrdinaryKriging3D;

/**
 * Estimasi blok model pit (D93-1): kadar voxel dari borehole lewat IDW; varians Kriging menentukan klasifikasi
 * (varians ≤ ambang → MEASURED, ≤ 2× ambang → INDICATED, selain itu INFERRED; kadar < cut-off → WASTE).
 */
final class EstimateBlockReserveAction
{
    private const DENSITY_KG_M3 = 1600;

    public function __construct(
        private readonly InverseDistanceWeightingInterpolator $idw,
        private readonly OrdinaryKriging3D $kriging,
    ) {}

    /**
     * @param  list<array{x_mm: int, y_mm: int, z_mm: int, size_mm: int}>  $grid
     * @return array{voxels: int, tonnage_kg: int, grade_ppm: int}
     */
    public function execute(string $pitId, array $grid, int $cutoffPpm, int $varianceThresholdBps): array
    {
        $pit = MiningPit::findOrFail($pitId);
        $samples = MiningBoreholeAssay::where('pit_id', $pit->id)->orderBy('hole_code')->orderBy('depth_from_mm')->get()
            ->map(fn ($b) => ['x' => $b->coord_x_mm, 'y' => $b->coord_y_mm, 'z' => $b->elevation_z_mm, 'grade_ppm' => $b->grade_ni_ppm])->all();

        if ($samples === []) {
            throw new DomainException("Pit {$pit->pit_code} belum memiliki borehole assay; estimasi cadangan ditolak.");
        }

        return DB::transaction(function () use ($pit, $grid, $samples, $cutoffPpm, $varianceThresholdBps) {
            $tonnage = 0;
            $metal = 0;

            foreach ($grid as $cell) {
                $target = ['x' => $cell['x_mm'], 'y' => $cell['y_mm'], 'z' => $cell['z_mm']];
                $grade = $this->idw->interpolate($target, $samples)->interpolatedGradePpm;
                $variance = $this->kriging->estimate($target, $samples)->krigingVarianceBps;

                $volumeCm3 = intdiv($cell['size_mm'] ** 3, 1000);
                $kg = intdiv($volumeCm3 * self::DENSITY_KG_M3, 1000000);

                $class = match (true) {
                    $grade < $cutoffPpm => 'WASTE',
                    $variance <= $varianceThresholdBps => 'MEASURED',
                    $variance <= 2 * $varianceThresholdBps => 'INDICATED',
                    default => 'INFERRED',
                };

                MiningBlockModelVoxel::updateOrCreate(
                    ['voxel_code' => sprintf('VX-%s-%d-%d-%d', $pit->pit_code, $cell['x_mm'], $cell['y_mm'], $cell['z_mm'])],
                    [
                        'pit_id' => $pit->id,
                        'center_x_mm' => $cell['x_mm'],
                        'center_y_mm' => $cell['y_mm'],
                        'center_z_mm' => $cell['z_mm'],
                        'volume_cm3' => $volumeCm3,
                        'density_kg_m3' => self::DENSITY_KG_M3,
                        'estimated_tonnage_kg' => $kg,
                        'estimated_grade_ppm' => $grade,
                        'classification' => $class,
                    ]
                );

                $tonnage += $kg;
                $metal += $kg * $grade;
            }

            return [
                'voxels' => count($grid),
                'tonnage_kg' => $tonnage,
                'grade_ppm' => $tonnage > 0 ? intdiv($metal, $tonnage) : 0,
            ];
        });
    }
}
