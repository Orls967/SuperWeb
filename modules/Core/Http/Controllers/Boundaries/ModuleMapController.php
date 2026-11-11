<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Boundaries;

use App\Http\Controllers\Controller;
use App\Quality\Modules\BoundariesScanner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Algorithms\Boundaries\AcyclicCondensationDecomposer;
use Modules\Core\Domain\Algorithms\Boundaries\CrossModuleBlastRadiusAnalyzer;
use Modules\Core\Domain\Algorithms\Boundaries\DependencyCondensationGraph;
use Modules\Core\Domain\Algorithms\Boundaries\ModuleCouplingMatrixScorer;
use Modules\Core\Domain\Algorithms\Boundaries\ModuleDependencyPathFinder;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

final class ModuleMapController extends Controller
{
    public function __construct(
        private readonly BoundariesScanner $scanner = new BoundariesScanner,
        private readonly CrossModuleBlastRadiusAnalyzer $blastAnalyzer = new CrossModuleBlastRadiusAnalyzer,
        private readonly ModuleCouplingMatrixScorer $couplingScorer = new ModuleCouplingMatrixScorer,
        private readonly DependencyCondensationGraph $condensationGraph = new DependencyCondensationGraph,
        private readonly AcyclicCondensationDecomposer $strataDecomposer = new AcyclicCondensationDecomposer,
        private readonly ModuleDependencyPathFinder $pathFinder = new ModuleDependencyPathFinder,
    ) {}

    public function index(Request $request): View
    {
        $analysis = $this->scanner->scan(base_path(), (array) config('modules'));

        $totalViolations = CoreBoundaryViolation::count();
        $openViolations = CoreBoundaryViolation::whereNotIn('status', [ViolationStatus::CLOSED->value, ViolationStatus::WAIVED->value])->count();
        $waivedViolations = CoreBoundaryViolation::where('status', ViolationStatus::WAIVED->value)->count();
        $closedViolations = CoreBoundaryViolation::where('status', ViolationStatus::CLOSED->value)->count();

        // Architectural coupling and strata analysis
        $couplingReport = $this->couplingScorer->compute($analysis['modules'], $analysis['dependencyGraph']);
        $condensationReport = $this->condensationGraph->analyze($analysis['modules'], $analysis['dependencyGraph']);
        $strataReport = $this->strataDecomposer->decompose($analysis['modules'], $analysis['dependencyGraph']);

        return view('core::boundaries.map', [
            'analysis' => $analysis,
            'stats' => [
                'total_modules' => $analysis['metrics']['totalModules'],
                'total_dependencies' => $analysis['metrics']['totalDependencies'],
                'coverage_permille' => $analysis['metrics']['globalCoveragePermille'],
                'cycles_count' => $analysis['metrics']['cyclesCount'],
                'total_violations' => $totalViolations,
                'open_violations' => $openViolations,
                'waived_violations' => $waivedViolations,
                'closed_violations' => $closedViolations,
                'critical_path_length' => $condensationReport['criticalPathLength'],
                'total_strata_layers' => $strataReport['totalLayers'],
            ],
            'modules' => $analysis['modules'],
            'perModuleScores' => $analysis['coverage']['perModuleScores'],
            'dependencyGraph' => $analysis['dependencyGraph'],
            'scc' => $analysis['scc'],
            'couplingReport' => $couplingReport,
            'condensationReport' => $condensationReport,
            'strataReport' => $strataReport,
        ]);
    }

    /**
     * API JSON endpoint for module blast radius preview.
     */
    public function blastRadius(Request $request, string $module): JsonResponse
    {
        $analysis = $this->scanner->scan(base_path(), (array) config('modules'));

        if (! in_array($module, $analysis['modules'], true)) {
            return response()->json(['error' => "Modul '{$module}' tidak ditemukan."], 404);
        }

        $report = $this->blastAnalyzer->analyze(
            $module,
            $analysis['dependencyGraph'],
            $analysis['coverage']['perModuleScores']
        );

        return response()->json($report);
    }

    /**
     * API JSON endpoint for dependency path finding between two modules.
     */
    public function path(Request $request, string $source, string $target): JsonResponse
    {
        $analysis = $this->scanner->scan(base_path(), (array) config('modules'));

        if (! in_array($source, $analysis['modules'], true) || ! in_array($target, $analysis['modules'], true)) {
            return response()->json(['error' => 'Modul sumber atau tujuan tidak valid.'], 404);
        }

        $maxDepth = (int) $request->input('max_depth', 6);
        $pathReport = $this->pathFinder->findPaths($source, $target, $analysis['dependencyGraph'], $maxDepth);

        return response()->json($pathReport);
    }
}
