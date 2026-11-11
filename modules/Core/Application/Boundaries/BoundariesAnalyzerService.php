<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use App\Quality\ArchScan\ArchScanner;
use App\Quality\ArchScan\ScanReport;
use App\Quality\Modules\ModuleRegistry;
use App\Quality\Support\NameReferences;
use App\Quality\Support\SourceFile;
use App\Quality\Support\SourceFinder;
use Modules\Core\Domain\Algorithms\Boundaries\ContractCoverageScorer;
use Modules\Core\Domain\Algorithms\Boundaries\DependencyMatrixTransitiveClosure;
use Modules\Core\Domain\Algorithms\Boundaries\StronglyConnectedComponents;
use Modules\Core\Domain\Algorithms\Boundaries\TablePrefixOwnershipResolver;
use Modules\Core\Domain\Enums\ViolationCategory;

/**
 * Service for static AST boundary analysis across all modules (DR4-2, W8-D41..D44).
 * Reuses arch:scan rules and token_get_all AST references without duplicate divergent logic.
 */
final class BoundariesAnalyzerService
{
    public function __construct(
        private readonly StronglyConnectedComponents $scc = new StronglyConnectedComponents,
        private readonly ContractCoverageScorer $coverageScorer = new ContractCoverageScorer,
        private readonly DependencyMatrixTransitiveClosure $closure = new DependencyMatrixTransitiveClosure,
    ) {}

    /**
     * Run full boundary analysis on the project.
     *
     * @param  list<string>  $paths
     * @return array{
     *     modules: list<string>,
     *     dependencyGraph: array<string, list<string>>,
     *     scc: array{components: list<list<string>>, condensationDag: array<int, list<int>>, hasCycles: bool, cycles: list<list<string>>},
     *     coverage: array{perModuleScores: array<string, int>, pairScores: array<string, int>, globalScore: int, totalContracts: int, totalViolations: int},
     *     violations: list<array{rule_id: string, category: string, file_path: string, line_number: int, signature: string, client_module: string, owner_module: string, message: string}>,
     *     metrics: array{totalModules: int, totalDependencies: int, totalViolations: int, globalCoveragePermille: int, cyclesCount: int}
     * }
     */
    public function analyze(string $basePath, ?array $config = null, array $paths = ['modules']): array
    {
        $config = $config ?? (array) config('modules', require $basePath.'/config/modules.php');
        $registry = ModuleRegistry::fromConfig($config);
        $prefixResolver = new TablePrefixOwnershipResolver(
            $config['table_prefixes'] ?? [],
            $config['legacy_tables'] ?? [],
            $config['shared_tables'] ?? [],
        );

        // 1. Discover all modules dynamically from directories
        $modulesDir = $basePath.'/modules';
        $moduleNames = [];
        if (is_dir($modulesDir)) {
            $entries = scandir($modulesDir) ?: [];
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..' && is_dir($modulesDir.'/'.$entry)) {
                    $moduleNames[] = $entry;
                }
            }
        }
        sort($moduleNames, SORT_STRING);

        // 2. Scan source files for module dependency edges and contract calls
        $dependencyGraph = [];
        $domainGraph = [];
        foreach ($moduleNames as $m) {
            $dependencyGraph[$m] = [];
            $domainGraph[$m] = [];
        }

        $contractCalls = [];

        foreach (SourceFinder::phpFiles($basePath, $paths, ['#(?:^|/)modules/[^/]+/tests/#']) as $file) {
            $clientModule = $file->module();

            if ($clientModule === null) {
                continue;
            }

            // Scan all imports and fully qualified names
            $imports = NameReferences::imports($file);
            $fqns = NameReferences::fullyQualified($file);

            foreach ([...$imports, ...$fqns] as $ref) {
                $name = $ref['name'];
                if (preg_match('#^Modules\\\\([^\\\\]+)\\\\#', $name, $matches) === 1) {
                    $targetModule = $matches[1];

                    if ($targetModule !== $clientModule && in_array($targetModule, $moduleNames, true)) {
                        if (! in_array($targetModule, $dependencyGraph[$clientModule], true)) {
                            $dependencyGraph[$clientModule][] = $targetModule;
                        }

                        // Check if this reference is a contract
                        if (str_contains($name, '\\Contracts\\')) {
                            $pair = "{$clientModule}:{$targetModule}";
                            $contractCalls[$pair] = ($contractCalls[$pair] ?? 0) + 1;
                        }

                        // If it is a cross-module domain import coupling (A1, outside shared kernel), track in domainGraph
                        if (str_contains($name, '\\Domain\\') && ! $registry->isSharedKernelNamespace($name)) {
                            if (! in_array($targetModule, $domainGraph[$clientModule], true)) {
                                $domainGraph[$clientModule][] = $targetModule;
                            }
                        }
                    }
                }
            }
        }

        foreach ($dependencyGraph as $m => &$targets) {
            sort($targets, SORT_STRING);
        }
        unset($targets);

        foreach ($domainGraph as $m => &$dTargets) {
            sort($dTargets, SORT_STRING);
        }
        unset($dTargets);

        // 3. Compute Tarjan SCC and cycle detection on domain dependency graph (DR4-2: SCC A1)
        $sccResult = $this->scc->compute($moduleNames, $domainGraph);
        $closureReport = $this->closure->compute($moduleNames, $domainGraph);

        // 4. Run ArchScanner rules to get real violations
        $archReport = ArchScanner::scanProject($basePath, $config, $paths);
        $extractedViolations = [];

        foreach ($archReport->violations('A1') as $v) {
            $file = new SourceFile($v->path, '');
            $client = $file->module() ?? 'Unknown';

            // Extract owner module from signature or message
            $owner = 'Core';
            if (preg_match('#Modules\\\\([^\\\\]+)\\\\Domain#', $v->signature, $m) === 1) {
                $owner = $m[1];
            }

            $extractedViolations[] = [
                'rule_id' => 'A1',
                'category' => ViolationCategory::DOMAIN_IMPORT->value,
                'file_path' => $v->path,
                'line_number' => $v->line,
                'signature' => $v->signature,
                'client_module' => $client,
                'owner_module' => $owner,
                'message' => $v->message,
            ];
        }

        foreach ($archReport->violations('A2') as $v) {
            $file = new SourceFile($v->path, '');
            $client = $file->module() ?? 'Unknown';

            // Extract table name from signature
            $owner = 'Unknown';
            if (preg_match("#['\"]([^'\"]+)['\"]#", $v->signature, $m) === 1) {
                $table = $m[1];
                $res = $prefixResolver->resolve($table, $client);
                $owner = $res->owner ?? 'Unknown';
            }

            $extractedViolations[] = [
                'rule_id' => 'A2',
                'category' => ViolationCategory::TABLE_PREFIX->value,
                'file_path' => $v->path,
                'line_number' => $v->line,
                'signature' => $v->signature,
                'client_module' => $client,
                'owner_module' => $owner,
                'message' => $v->message,
            ];
        }

        // Add A3 violations if cycles exist
        foreach ($sccResult['cycles'] as $cycle) {
            $cycleStr = implode(' -> ', $cycle).' -> '.$cycle[0];
            $extractedViolations[] = [
                'rule_id' => 'A3',
                'category' => ViolationCategory::CIRCULAR_DEPENDENCY->value,
                'file_path' => 'modules/'.($cycle[0] ?? 'Core'),
                'line_number' => 1,
                'signature' => "Cycle: {$cycleStr}",
                'client_module' => $cycle[0] ?? 'Unknown',
                'owner_module' => $cycle[1] ?? $cycle[0] ?? 'Unknown',
                'message' => "Circular dependency detected: {$cycleStr}",
            ];
        }

        // 5. Compute contract coverage
        $coverageViolations = array_map(fn (array $v): array => [
            'client' => $v['client_module'],
            'owner' => $v['owner_module'],
            'rule_id' => $v['rule_id'],
        ], $extractedViolations);

        $coverageResult = $this->coverageScorer->calculate($contractCalls, $coverageViolations);

        $totalDependencies = 0;
        foreach ($dependencyGraph as $targets) {
            $totalDependencies += count($targets);
        }

        return [
            'modules' => $moduleNames,
            'dependencyGraph' => $dependencyGraph,
            'scc' => $sccResult,
            'coverage' => $coverageResult,
            'violations' => $extractedViolations,
            'metrics' => [
                'totalModules' => count($moduleNames),
                'totalDependencies' => $totalDependencies,
                'totalViolations' => count($extractedViolations),
                'globalCoveragePermille' => $coverageResult['globalScore'],
                'cyclesCount' => count($sccResult['cycles']),
            ],
        ];
    }
}
