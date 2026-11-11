<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use App\Quality\Support\NameReferences;
use App\Quality\Support\SourceFinder;

/**
 * Service to detect contract drift, orphaned interfaces, and binding health (DR4-4, DR4-6).
 *
 * Scans all module contracts, verifies container bindings and implementations,
 * and flags interfaces without active callsites.
 */
final class ModuleContractDriftDetector
{
    /**
     * Run contract drift detection across all modules.
     *
     * @param  list<string>  $paths
     * @return array{
     *     totalContracts: int,
     *     boundContracts: int,
     *     orphanedContracts: list<string>,
     *     unboundContracts: list<string>,
     *     contractUsageCount: array<string, int>,
     *     driftScorePermille: int,
     *     healthRating: string
     * }
     */
    public function detect(string $basePath, array $paths = ['modules']): array
    {
        $contractFiles = [];
        $files = SourceFinder::phpFiles($basePath, $paths, ['#(?:^|/)modules/[^/]+/tests/#']);

        // 1. Identify all interface contracts under Modules/*/Contracts/
        foreach ($files as $file) {
            if (str_contains($file->relativePath, '/Contracts/') && ! str_contains($file->relativePath, '/tests/')) {
                $contractFiles[] = $file;
            }
        }

        $allContractClasses = [];
        $contractUsageCount = [];

        foreach ($contractFiles as $file) {
            $tokens = $file->tokens();
            $namespace = '';
            $interfaceName = '';

            for ($i = 0; $i < count($tokens); $i++) {
                if ($tokens[$i]->is(T_NAMESPACE)) {
                    $nsTokens = [];
                    for ($j = $i + 1; $j < count($tokens) && ! $tokens[$j]->is(';'); $j++) {
                        if ($tokens[$j]->is(T_NAME_QUALIFIED) || $tokens[$j]->is(T_STRING)) {
                            $nsTokens[] = $tokens[$j]->text;
                        }
                    }
                    $namespace = implode('\\', $nsTokens);
                }

                if ($tokens[$i]->is(T_INTERFACE)) {
                    for ($j = $i + 1; $j < count($tokens); $j++) {
                        if ($tokens[$j]->is(T_STRING)) {
                            $interfaceName = $tokens[$j]->text;
                            break;
                        }
                    }
                }
            }

            if ($namespace !== '' && $interfaceName !== '') {
                $fqn = "{$namespace}\\{$interfaceName}";
                $allContractClasses[] = $fqn;
                $contractUsageCount[$fqn] = 0;
            }
        }

        sort($allContractClasses, SORT_STRING);

        // 2. Scan all files for usage of these contracts
        foreach ($files as $file) {
            $imports = NameReferences::imports($file);
            $fqns = NameReferences::fullyQualified($file);

            foreach ([...$imports, ...$fqns] as $ref) {
                $name = $ref['name'];
                if (isset($contractUsageCount[$name])) {
                    $contractUsageCount[$name]++;
                }
            }
        }

        // 3. Check container bindings and orphan status
        $orphanedContracts = [];
        $unboundContracts = [];
        $boundCount = 0;

        foreach ($allContractClasses as $contractFqn) {
            $usage = $contractUsageCount[$contractFqn] ?? 0;
            if ($usage === 0) {
                $orphanedContracts[] = $contractFqn;
            }

            if (app()->bound($contractFqn)) {
                $boundCount++;
            } else {
                $unboundContracts[] = $contractFqn;
            }
        }

        $total = max(1, count($allContractClasses));
        $usedCount = $total - count($orphanedContracts);
        $driftScore = (int) floor(($usedCount * 1000) / $total);

        $healthRating = $driftScore >= 900
            ? 'PRISTINE'
            : ($driftScore >= 700 ? 'GOOD' : ($driftScore >= 500 ? 'DEGRADED' : 'DRIFTED'));

        return [
            'totalContracts' => count($allContractClasses),
            'boundContracts' => $boundCount,
            'orphanedContracts' => $orphanedContracts,
            'unboundContracts' => $unboundContracts,
            'contractUsageCount' => $contractUsageCount,
            'driftScorePermille' => $driftScore,
            'healthRating' => $healthRating,
        ];
    }
}
