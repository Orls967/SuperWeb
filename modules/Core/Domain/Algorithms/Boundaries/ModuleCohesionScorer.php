<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Deterministic Package Relational Cohesion Scorer (Martin, 1994; Stevens, 1974).
 *
 * Evaluates internal relational cohesion:
 * H = (R + 1) / N
 * where:
 * - R = Number of internal internal references/relationships between classes in the module
 * - N = Number of classes/types defined within the module
 * - H is scaled to integer permille [0..5000]
 *
 * High relational cohesion (H >= 1000 permille) indicates a well-encapsulated cohesive module.
 * Low relational cohesion (H < 500 permille) indicates an unfocused, loosely bound module.
 */
final class ModuleCohesionScorer
{
    public function __construct(
        private readonly CohesionCertificateVerifier $verifier = new CohesionCertificateVerifier,
    ) {}

    /**
     * Compute relational cohesion across modules.
     *
     * @param  array<string, array{totalClasses: int, internalRelations: int}>  $moduleProfiles
     * @return array{
     *     perModuleCohesion: array<string, array{
     *         totalClasses: int,
     *         internalRelations: int,
     *         cohesionPermille: int,
     *         cohesionLevel: string
     *     }>,
     *     averageCohesionPermille: int,
     *     exemplaryModules: list<string>,
     *     fragmentedModules: list<string>
     * }
     */
    public function calculate(array $moduleProfiles): array
    {
        $perModule = [];
        $totalCohesion = 0;
        $exemplary = [];
        $fragmented = [];

        ksort($moduleProfiles);

        foreach ($moduleProfiles as $module => $profile) {
            $n = max(1, $profile['totalClasses']);
            $r = max(0, $profile['internalRelations']);

            // Relational cohesion formula: H = (R + 1) / N * 1000
            $cohesionPermille = (int) floor((($r + 1) * 1000) / $n);
            $cohesionPermille = min(5000, $cohesionPermille); // cap at 5.0 ratio

            if ($cohesionPermille >= 1500) {
                $level = 'HIGHLY_COHESIVE';
                $exemplary[] = $module;
            } elseif ($cohesionPermille >= 800) {
                $level = 'ADEQUATELY_COHESIVE';
            } else {
                $level = 'FRAGMENTED_LOW_COHESION';
                $fragmented[] = $module;
            }

            $perModule[$module] = [
                'totalClasses' => $n,
                'internalRelations' => $r,
                'cohesionPermille' => $cohesionPermille,
                'cohesionLevel' => $level,
            ];

            $totalCohesion += $cohesionPermille;
        }

        $totalModules = max(1, count($moduleProfiles));
        $avgCohesion = (int) floor($totalCohesion / $totalModules);

        sort($exemplary, SORT_STRING);
        sort($fragmented, SORT_STRING);

        $report = [
            'perModuleCohesion' => $perModule,
            'averageCohesionPermille' => $avgCohesion,
            'exemplaryModules' => $exemplary,
            'fragmentedModules' => $fragmented,
        ];

        $this->verifier->verify($report);

        return $report;
    }
}
