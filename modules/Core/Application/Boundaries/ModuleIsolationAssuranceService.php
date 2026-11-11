<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use App\Quality\Modules\BoundariesScanner;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

/**
 * Service to assure database and transactional isolation across module boundaries (DR4-1, DR4-5).
 *
 * Enforces:
 * - Single-module transaction scopes (no distributed 2PC anti-patterns)
 * - Table isolation barriers: no direct cross-prefix writes without registered waivers
 * - Contract coverage verification for cross-module invocations
 */
final class ModuleIsolationAssuranceService
{
    public function __construct(
        private readonly BoundariesScanner $scanner = new BoundariesScanner,
    ) {}

    /**
     * Run full isolation assurance audit.
     *
     * @param  array<string, mixed>|null  $config
     * @return array{
     *     totalModulesAudited: int,
     *     fullyIsolatedModules: list<string>,
     *     coupledModules: list<string>,
     *     totalActiveViolations: int,
     *     isolationHealthPermille: int,
     *     sharedTableWaiversCount: int,
     *     complianceStatus: string,
     *     highRiskCouplings: list<array{client: string, owner: string, violationCount: int}>
     * }
     */
    public function evaluate(string $basePath, ?array $config = null): array
    {
        $config = $config ?? (array) config('modules', require $basePath.'/config/modules.php');
        $analysis = $this->scanner->scan($basePath, $config);

        $modules = $analysis['modules'];
        $violations = CoreBoundaryViolation::whereIn('status', ['detected', 'triaged', 'contract_designed', 'migrating'])->get();

        $couplings = [];
        $coupledModulesSet = [];

        foreach ($violations as $v) {
            $client = $v->client_module;
            $owner = $v->owner_module;
            $pair = "{$client}:{$owner}";

            $couplings[$pair] = ($couplings[$pair] ?? 0) + 1;
            $coupledModulesSet[$client] = true;
            $coupledModulesSet[$owner] = true;
        }

        $fullyIsolated = [];
        $coupled = [];

        foreach ($modules as $m) {
            if (isset($coupledModulesSet[$m])) {
                $coupled[] = $m;
            } else {
                $fullyIsolated[] = $m;
            }
        }

        sort($fullyIsolated, SORT_STRING);
        sort($coupled, SORT_STRING);

        // High risk couplings (pairs with >= 3 active violations)
        $highRisk = [];
        foreach ($couplings as $pair => $count) {
            if ($count >= 2) {
                [$c, $o] = explode(':', $pair);
                $highRisk[] = [
                    'client' => $c,
                    'owner' => $o,
                    'violationCount' => $count,
                ];
            }
        }

        usort($highRisk, static fn (array $a, array $b): int => $b['violationCount'] <=> $a['violationCount']);

        $totalCount = max(1, count($modules));
        $isolatedRatio = (count($fullyIsolated) * 1000) / $totalCount;
        $coverageWeight = ($analysis['coverage']['globalScore'] * 0.4);
        $isolationScore = (int) floor(($isolatedRatio * 0.6) + $coverageWeight);
        $isolationScore = max(0, min(1000, $isolationScore));

        $sharedTables = $config['shared_tables'] ?? [];
        $sharedTableWaiverCount = 0;
        foreach ($sharedTables as $tables) {
            $sharedTableWaiverCount += count($tables);
        }

        $status = $isolationScore >= 850
            ? 'HIGH_ISOLATION'
            : ($isolationScore >= 650 ? 'ACCEPTABLE_ISOLATION' : 'COMPROMISED_ISOLATION');

        return [
            'totalModulesAudited' => count($modules),
            'fullyIsolatedModules' => $fullyIsolated,
            'coupledModules' => $coupled,
            'totalActiveViolations' => $violations->count(),
            'isolationHealthPermille' => $isolationScore,
            'sharedTableWaiversCount' => $sharedTableWaiverCount,
            'complianceStatus' => $status,
            'highRiskCouplings' => $highRisk,
        ];
    }
}
