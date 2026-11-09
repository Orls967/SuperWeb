<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Algorithms\CanaryErrorEvaluator;
use Modules\Platform\Domain\Models\CanaryObservation;
use Modules\Platform\Domain\Models\ChangeDeployment;

class CanaryEvaluateCommand extends Command
{
    protected $signature = 'platform:canary-evaluate
                            {deployment_id : ID Deployment yang dievaluasi}
                            {--baseline_req=10000 : Jumlah request baseline}
                            {--baseline_err=20 : Jumlah error baseline}
                            {--canary_req=1000 : Jumlah request canary}
                            {--canary_err=5 : Jumlah error canary}';

    protected $description = 'Mengevaluasi distribusi statistik error rate canary deployment vs baseline';

    public function handle(CanaryErrorEvaluator $evaluator): int
    {
        $depId = (int) $this->argument('deployment_id');
        $deployment = ChangeDeployment::find($depId);

        if ($deployment === null) {
            $this->error("Deployment #{$depId} tidak ditemukan.");

            return 1;
        }

        $baseReq = (int) $this->option('baseline_req');
        $baseErr = (int) $this->option('baseline_err');
        $canReq = (int) $this->option('canary_req');
        $canErr = (int) $this->option('canary_err');

        $result = $evaluator->evaluate([
            'baseline_requests' => $baseReq,
            'baseline_errors' => $baseErr,
            'canary_requests' => $canReq,
            'canary_errors' => $canErr,
        ]);

        CanaryObservation::create([
            'deployment_id' => $deployment->id,
            'sample_window_start' => now()->subMinutes(10),
            'sample_window_end' => now(),
            'baseline_requests' => $baseReq,
            'baseline_errors' => $baseErr,
            'canary_requests' => $canReq,
            'canary_errors' => $canErr,
            'z_score' => (int) round($result->zScore * 1000),
            'relative_risk_bps' => (int) round($result->relativeRisk * 10000),
            'recommendation' => $result->recommendation->value,
        ]);

        $this->info("Evaluasi Canary Selesai untuk Deployment #{$depId}:");
        $this->line('  - Baseline Error Rate: '.round($result->baselineErrorRate * 100, 3).'%');
        $this->line('  - Canary Error Rate: '.round($result->canaryErrorRate * 100, 3).'%');
        $this->line("  - Z-Score Stat: {$result->zScore}");
        $this->line("  - Rekomendasi: {$result->recommendation->value}");
        $this->line("  - Catatan: {$result->analysisSummary}");

        return $result->shouldRollback() ? 2 : 0;
    }
}
