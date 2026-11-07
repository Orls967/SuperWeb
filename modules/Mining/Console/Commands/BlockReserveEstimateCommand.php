<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\EstimateBlockReserveAction;
use Modules\Mining\Domain\Models\MiningPit;

class BlockReserveEstimateCommand extends Command
{
    protected $signature = 'mining:estimate-reserve {pit_id : UUID atau Pit Code}';

    protected $description = 'Menjalankan estimasi cadangan blok tambang berbasis 3D Voxel Grid & IDW interpolator';

    public function handle(EstimateBlockReserveAction $action): int
    {
        $pitIdentifier = (string) $this->argument('pit_id');

        $pit = MiningPit::where('id', $pitIdentifier)
            ->orWhere('pit_code', $pitIdentifier)
            ->first();

        if (! $pit) {
            $this->error("Pit dengan ID/Kode '{$pitIdentifier}' tidak ditemukan.");
            return 1;
        }

        $this->info("Menjalankan interpolasi voxel 3D untuk Pit {$pit->pit_code}...");

        // Generate grid 3x3x2 voxel sederhana untuk simulasi CLI
        $grid = [];
        for ($x = 0; $x < 3; $x++) {
            for ($y = 0; $y < 3; $y++) {
                for ($z = 0; $z < 2; $z++) {
                    $grid[] = [
                        'x_mm' => $x * 25000,
                        'y_mm' => $y * 25000,
                        'z_mm' => $z * 10000,
                        'size_mm' => 25000,
                    ];
                }
            }
        }

        $res = $action->execute($pit->id, $grid);

        $this->table(
            ['Metrik Estimasi', 'Hasil'],
            [
                ['Pit Code', $pit->pit_code],
                ['Jumlah Voxel Ter-generate', $res['total_voxels']],
                ['Total Cadangan (Ton)', number_format($res['total_tonnage_kg'] / 1000.0, 2)],
                ['Rata-rata Kadar Ni (ppm)', $res['average_grade_ppm']],
                ['Estimasi Kadar Ni (%)', number_format($res['average_grade_ppm'] / 10000.0, 3).'%'],
            ]
        );

        $this->info('Estimasi cadangan blok tambang selesai dengan sukses.');

        return 0;
    }
}
