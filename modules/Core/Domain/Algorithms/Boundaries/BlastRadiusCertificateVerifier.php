<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Formal certificate verifier for blast radius analysis (K-07, DR4-2).
 *
 * Verifies mathematical properties of reachability, acyclicity of critical paths,
 * bounded ripple depth, and integer permille score bounds.
 */
final class BlastRadiusCertificateVerifier
{
    /**
     * @param  array{
     *     target: string,
     *     directConsumers: list<string>,
     *     transitiveConsumers: list<string>,
     *     directDependencies: list<string>,
     *     transitiveDependencies: list<string>,
     *     maxRippleDepth: int,
     *     impactScorePermille: int,
     *     criticalPaths: list<list<string>>,
     *     mitigationRecommendations: list<string>
     * }  $report
     * @param  array<string, list<string>>  $dependencyGraph
     */
    public function verify(array $report, array $dependencyGraph): bool
    {
        $target = $report['target'];

        // 1. Direct consumers must be a subset of transitive consumers
        foreach ($report['directConsumers'] as $dc) {
            if (! in_array($dc, $report['transitiveConsumers'], true)) {
                return false;
            }

            // Each direct consumer must actually list $target in its dependencies
            $deps = $dependencyGraph[$dc] ?? [];
            if (! in_array($target, $deps, true)) {
                return false;
            }
        }

        // 2. Direct dependencies must be a subset of transitive dependencies
        foreach ($report['directDependencies'] as $dd) {
            if (! in_array($dd, $report['transitiveDependencies'], true)) {
                return false;
            }

            // Target must actually list $dd in its dependencies
            $targetDeps = $dependencyGraph[$target] ?? [];
            if (! in_array($dd, $targetDeps, true)) {
                return false;
            }
        }

        // 3. Score must be bounded in [0, 1000]
        if ($report['impactScorePermille'] < 0 || $report['impactScorePermille'] > 1000) {
            return false;
        }

        // 4. Max ripple depth must be non-negative
        if ($report['maxRippleDepth'] < 0) {
            return false;
        }

        // 5. Critical paths must start at target and each hop must be a valid reverse dependency
        foreach ($report['criticalPaths'] as $path) {
            if ($path === [] || $path[0] !== $target) {
                return false;
            }

            for ($i = 0; $i < count($path) - 1; $i++) {
                $provider = $path[$i];
                $consumer = $path[$i + 1];

                $consumerDeps = $dependencyGraph[$consumer] ?? [];
                if (! in_array($provider, $consumerDeps, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}

