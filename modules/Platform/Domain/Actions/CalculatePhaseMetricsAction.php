<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use App\Quality\Algorithms\ClassReachabilityGraph;
use App\Quality\Algorithms\DecisionDensityCounter;
use App\Quality\Algorithms\DuplicateWindowDetector;
use Modules\Platform\Domain\Models\PhaseMetricSnapshot;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Kalkulasi Metrik Fase Resmi & Penegakan Aturan Anti-Padding T-10 (M-2 s/d M-5).
 */
class CalculatePhaseMetricsAction
{
    public function __construct(
        protected DuplicateWindowDetector $duplicateDetector = new DuplicateWindowDetector(windowSize: 35),
        protected DecisionDensityCounter $densityCounter = new DecisionDensityCounter(),
        protected ClassReachabilityGraph $reachabilityGraph = new ClassReachabilityGraph()
    ) {
    }

    /**
     * @return array{
     *     phase_id: string,
     *     b1_loc: int,
     *     b2_loc: int,
     *     b3_loc: int,
     *     decision_density: float,
     *     duplicate_ratio: float,
     *     orphan_count: int,
     *     status: string,
     *     violations: list<string>,
     *     anemic_ratio: float,
     *     certificate: string
     * }
     */
    public function calculate(string $path, string $phaseId = 'current'): array
    {
        $targetDir = base_path($path);
        if (! is_dir($targetDir)) {
            $targetDir = $path;
        }

        $phpFiles = [];
        if (is_dir($targetDir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($targetDir));
            $regex = new RegexIterator($iterator, '/^.+\.php$/i');
            foreach ($regex as $f) {
                $phpFiles[] = $f->getRealPath();
            }
        } elseif (is_file($targetDir)) {
            $phpFiles[] = $targetDir;
        }

        $totalLines = 0;
        $totalDecisionPoints = 0;
        $totalStatements = 0;
        $anemicClasses = 0;
        $totalClasses = 0;
        $duplicateBlocksCount = 0;
        $duplicateTokensCount = 0;
        $allTokensCount = 0;

        foreach ($phpFiles as $file) {
            $code = (string) @file_get_contents($file);
            $lines = count(explode("\n", $code));
            $totalLines += $lines;

            // A1 Analysis
            $dupReport = $this->duplicateDetector->analyzeCode($code, $file);
            if ($dupReport->hasDuplicates()) {
                $duplicateBlocksCount += $dupReport->duplicateCount;
                $duplicateTokensCount += (int) ($dupReport->duplicateRatio * $dupReport->totalLinesAnalyzed);
            }
            $allTokensCount += $dupReport->totalLinesAnalyzed;

            // A2 Analysis
            $metrics = $this->densityCounter->analyze($code);
            $totalDecisionPoints += $metrics->decisionPoints;
            $totalStatements += $metrics->executableStatements;
            if ($metrics->propertyCount > 0 || $metrics->methodCount > 0) {
                $totalClasses++;
                if ($metrics->isAnemic()) {
                    $anemicClasses++;
                }
            }
        }

        // A3 Analysis
        $this->reachabilityGraph->buildGraph([$targetDir]);
        $orphans = $this->reachabilityGraph->detectOrphans();
        $orphanCount = count($orphans);

        $density = $totalStatements > 0 ? (float) ($totalDecisionPoints / $totalStatements) : 0.0;
        $dupRatio = $allTokensCount > 0 ? (float) ($duplicateTokensCount / $allTokensCount) : 0.0;
        $anemicRatio = $totalClasses > 0 ? (float) ($anemicClasses / $totalClasses) : 0.0;

        $violations = [];

        // Aturan T-10:
        // M-3: Rasio DTO anemik tanpa logika tidak boleh > 20%
        if ($anemicRatio > 0.20) {
            $violations[] = sprintf('M-3: Terdeteksi rasio kelas anemik/tanpa logika %.1f%% (> 20%%)', $anemicRatio * 100);
        }

        // M-4: Duplikasi blok token (M-4 > 0)
        if ($duplicateBlocksCount > 0) {
            $violations[] = sprintf('M-4: Terdeteksi %d blok token terduplikasi (rasio %.1f%%)', $duplicateBlocksCount, $dupRatio * 100);
        }

        // M-5: Kelas yatim tidak terjangkau (M-5 > 0)
        if ($orphanCount > 0) {
            $violations[] = sprintf('M-5: Terdeteksi %d kelas yatim tanpa dependency path dari root (%s)', $orphanCount, implode(', ', array_slice($orphans, 0, 3)));
        }

        $status = empty($violations) ? 'compliant' : 'non_compliant';
        $cert = hash('sha256', "phase:{$phaseId}:loc:{$totalLines}:density:{$density}:dup:{$dupRatio}:orphans:{$orphanCount}");

        $snapshot = [
            'phase_id' => $phaseId,
            'b1_loc' => $totalLines,
            'b2_loc' => 0,
            'b3_loc' => 0,
            'decision_density' => round($density, 4),
            'duplicate_ratio' => round($dupRatio, 4),
            'orphan_count' => $orphanCount,
            'status' => $status,
            'violations' => $violations,
            'anemic_ratio' => round($anemicRatio, 4),
            'certificate' => $cert,
        ];

        PhaseMetricSnapshot::updateOrCreate(
            ['phase_id' => $phaseId],
            [
                'b1_loc' => $totalLines,
                'b2_loc' => 0,
                'b3_loc' => 0,
                'decision_density' => $snapshot['decision_density'],
                'duplicate_ratio' => $snapshot['duplicate_ratio'],
                'orphan_count' => $orphanCount,
                'status' => $status,
                'certificate_hash' => $cert,
                'breakdown' => $snapshot,
            ]
        );

        return $snapshot;
    }
}

