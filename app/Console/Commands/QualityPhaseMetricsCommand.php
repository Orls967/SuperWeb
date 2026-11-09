<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Actions\CalculatePhaseMetricsAction;

class QualityPhaseMetricsCommand extends Command
{
    protected $signature = 'quality:phase-metrics 
                            {--path=app : Direktori kode yang diukur} 
                            {--phase=current : ID Fase yang diukur} 
                            {--check : Flag penegakan gerbang ketat anti-padding T-10}';

    protected $description = 'Mengukur metrik fase B1-B3, cyclomatic density, serta mendeteksi manipulasi kode M-2 s/d M-5';

    public function handle(CalculatePhaseMetricsAction $action): int
    {
        $path = (string) $this->option('path');
        $phaseId = (string) $this->option('phase');
        $isCheck = (bool) $this->option('check');

        $this->info("Menghitung metrik kualitas kode untuk: {$path} (Fase: {$phaseId})...");

        $result = $action->calculate($path, $phaseId);

        $this->table(
            ['Metrik', 'Nilai', 'Standar'],
            [
                ['Total Baris (LOC)', $result['b1_loc'], '≥ target fase'],
                ['Decision Density', $result['decision_density'], '≥ 0.05'],
                ['Duplicate Ratio', sprintf('%.2f%%', $result['duplicate_ratio'] * 100), '0.00%'],
                ['Anemic DTO Ratio', sprintf('%.2f%%', $result['anemic_ratio'] * 100), '≤ 20.00%'],
                ['Orphan Classes', $result['orphan_count'], '0'],
                ['Status', $result['status'], 'compliant'],
                ['Certificate SHA-256', substr($result['certificate'], 0, 16).'...', 'valid'],
            ]
        );

        if (! empty($result['violations'])) {
            $this->error('Pelanggaran standar kualitas anti-padding terdeteksi:');
            foreach ($result['violations'] as $violation) {
                $this->line("  - {$violation}");
            }

            if ($isCheck) {
                return 1;
            }
        } else {
            $this->info('✅ Seluruh kriteria kualitas dan integritas anti-padding terpenuhi.');
        }

        return 0;
    }
}
