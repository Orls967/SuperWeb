<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use App\Quality\Modules\BoundariesScanner;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

/**
 * Executive compliance scorecard and SLA monitoring for architectural boundaries (DR4-1, DR4-8).
 *
 * Computes:
 * - Module compliance grades (A+ through F) based on contract coverage and violation counts
 * - Waiver SLA breach analysis (Breached, Near-Breach <= 7 days, Compliant)
 * - Platform modularity index (0..1000 permille)
 */
final class BoundaryComplianceReportService
{
    public function __construct(
        private readonly BoundariesScanner $scanner = new BoundariesScanner,
        private readonly ModuleIsolationAssuranceService $isolationService = new ModuleIsolationAssuranceService,
    ) {}

    /**
     * Generate full compliance scorecard report.
     *
     * @return array{
     *     platformModularityIndex: int,
     *     executiveRating: string,
     *     totalViolationsTracked: int,
     *     activeWaiversCount: int,
     *     slaStatus: array{breachedCount: int, nearBreachCount: int, compliantCount: int},
     *     moduleGrades: array<string, array{grade: string, coveragePermille: int, activeViolations: int, riskStatus: string}>,
     *     topPriorityActions: list<string>
     * }
     */
    public function generateReport(string $basePath): array
    {
        $isolationReport = $this->isolationService->evaluate($basePath);
        $analysis = $this->scanner->scan($basePath, (array) config('modules'));
        $violations = CoreBoundaryViolation::all();

        $activeWaivers = $violations->where('status', 'waived');
        $now = now();

        $breachedCount = 0;
        $nearBreachCount = 0;
        $compliantCount = 0;

        foreach ($activeWaivers as $w) {
            if ($w->expires_at === null || $w->expires_at->isPast()) {
                $breachedCount++;
            } elseif ($now->diffInDays($w->expires_at) <= 7) {
                $nearBreachCount++;
            } else {
                $compliantCount++;
            }
        }

        // Module grade evaluation
        $moduleGrades = [];
        $activeViolationsByModule = [];

        foreach ($violations as $v) {
            $status = $v->status instanceof \BackedEnum ? $v->status->value : (string) $v->status;
            if (in_array($status, ['detected', 'triaged', 'contract_designed', 'migrating'], true)) {
                $activeViolationsByModule[$v->client_module] = ($activeViolationsByModule[$v->client_module] ?? 0) + 1;
            }
        }

        $scores = $analysis['coverage']['perModuleScores'];
        $modules = $analysis['modules'];

        foreach ($modules as $m) {
            $cov = $scores[$m] ?? 1000;
            $activeCount = $activeViolationsByModule[$m] ?? 0;

            if ($cov >= 950 && $activeCount === 0) {
                $grade = 'A+';
                $risk = 'EXEMPLARY';
            } elseif ($cov >= 850 && $activeCount === 0) {
                $grade = 'A';
                $risk = 'EXCELLENT';
            } elseif ($cov >= 700 && $activeCount <= 2) {
                $grade = 'B';
                $risk = 'STABLE';
            } elseif ($cov >= 500 && $activeCount <= 5) {
                $grade = 'C';
                $risk = 'MODERATE_RISK';
            } elseif ($activeCount <= 10) {
                $grade = 'D';
                $risk = 'HIGH_RISK';
            } else {
                $grade = 'F';
                $risk = 'CRITICAL_RISK';
            }

            $moduleGrades[$m] = [
                'grade' => $grade,
                'coveragePermille' => $cov,
                'activeViolations' => $activeCount,
                'riskStatus' => $risk,
            ];
        }

        // Platform modularity index (0..1000)
        $globalCoverage = $analysis['coverage']['globalScore'];
        $waiverPenalty = min(300, $breachedCount * 50 + $nearBreachCount * 10);
        $platformIndex = max(0, min(1000, $globalCoverage - $waiverPenalty));

        $executiveRating = $platformIndex >= 900
            ? 'PRISTINE_MODULARITY'
            : ($platformIndex >= 750 ? 'STRONG_MODULARITY' : ($platformIndex >= 600 ? 'ACCEPTABLE_MODULARITY' : 'DEGRADED_MODULARITY'));

        // Top priority recommendations
        $priorityActions = [];
        if ($breachedCount > 0) {
            $priorityActions[] = "Segera jalankan 'boundary:expire-waivers' untuk membersihkan {$breachedCount} waiver kedaluwarsa.";
        }
        if ($nearBreachCount > 0) {
            $priorityActions[] = "Terdapat {$nearBreachCount} waiver yang mendekati kedaluwarsa (<= 7 hari). Lakukan refaktor atau perpanjangan resmi.";
        }
        if ($analysis['metrics']['totalViolations'] > 0) {
            $priorityActions[] = "Tuntaskan migrasi kontrak untuk {$analysis['metrics']['totalViolations']} pelanggaran yang masih aktif.";
        }
        if ($priorityActions === []) {
            $priorityActions[] = 'Seluruh invarian arsitektur berada dalam kepatuhan penuh. Pertahankan status green.';
        }

        return [
            'platformModularityIndex' => $platformIndex,
            'executiveRating' => $executiveRating,
            'totalViolationsTracked' => $violations->count(),
            'activeWaiversCount' => $activeWaivers->count(),
            'slaStatus' => [
                'breachedCount' => $breachedCount,
                'nearBreachCount' => $nearBreachCount,
                'compliantCount' => $compliantCount,
            ],
            'moduleGrades' => $moduleGrades,
            'topPriorityActions' => $priorityActions,
        ];
    }
}
