<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Domain\Services\GySamplingTheoryEvaluator;

class AuditGySamplingCommand extends Command
{
    protected $signature = 'mining:check-sampling {--lot=10000 : Massa lot (kg)} {--sample=50 : Massa sampel (kg)} {--d95=50 : Ukuran butir d95 (mm)}';

    protected $description = 'Audit kepatuhan statistik teori sampling Pierre Gy atas protokol pengambilan sampel bijih dan batubara';

    public function handle(GySamplingTheoryEvaluator $evaluator): int
    {
        $lotKg = (float) $this->option('lot');
        $sampleKg = (float) $this->option('sample');
        $d95 = (float) $this->option('d95');

        $this->info("Menjalankan audit Teori Sampling Pierre Gy (Lot: {$lotKg} kg, Sampel: {$sampleKg} kg, d95: {$d95} mm)...");

        $result = $evaluator->evaluate([
            'lot_mass_kg' => $lotKg,
            'sample_mass_kg' => $sampleKg,
            'top_size_d95_mm' => $d95,
        ]);

        $this->line("Konstanta Sampling (C) : {$result['sampling_constant_c']}");
        $this->line("RSD Galat Fundamental  : {$result['relative_standard_deviation_pct']}%");
        $this->line("Sampel Minimum Dianjurkan : {$result['recommended_minimum_sample_kg']} kg");
        $this->line("Keputusan Evaluasi     : {$result['grade_risk_verdict']}");

        if (! $result['is_sampling_statistically_representative']) {
            $this->warn('PERINGATAN: Protokol sampling di bawah batas representasi ISO 13909.');

            return 1;
        }

        $this->info('Audit Teori Sampling Sukses: Sampel terbukti representatif.');

        return 0;
    }
}
