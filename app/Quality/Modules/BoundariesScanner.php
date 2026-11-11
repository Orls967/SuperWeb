<?php

declare(strict_types=1);

namespace App\Quality\Modules;

use Modules\Core\Application\Boundaries\BoundariesAnalyzerService;

/**
 * Static scanner adapter for architecture boundaries in app/Quality (IZIN-1, DR4-2).
 */
final class BoundariesScanner
{
    public function __construct(
        private readonly BoundariesAnalyzerService $analyzer = new BoundariesAnalyzerService,
    ) {}

    /**
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
    public function scan(string $basePath, ?array $config = null, array $paths = ['modules']): array
    {
        return $this->analyzer->analyze($basePath, $config, $paths);
    }
}

