<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

final class ChangeImpactAnalyzer
{
    /**
     * Map of modules and their direct downstream dependencies across the enterprise monolith.
     *
     * @var array<string, list<string>>
     */
    private const DEPENDENCY_GRAPH = [
        'Core' => ['Banking', 'Finance', 'Treasury', 'Payment', 'Identity', 'SupplyChain', 'Logistics', 'Hospital', 'Hotel', 'Mall', 'AutoDex', 'Circular'],
        'Banking' => ['Finance', 'Treasury', 'Payment', 'Mall', 'Hospital'],
        'Finance' => ['Treasury', 'Payment', 'SupplyChain', 'Mall', 'AutoDex'],
        'Treasury' => ['Banking', 'Payment'],
        'Payment' => ['Mall', 'Hotel', 'Hospital', 'AutoDex', 'Circular'],
        'Identity' => ['Core', 'Banking', 'Finance', 'Hospital', 'Hotel', 'Mall'],
        'SupplyChain' => ['Logistics', 'Mall', 'Hospital'],
        'Logistics' => ['SupplyChain', 'Mall', 'Circular'],
        'Platform' => ['Core'],
    ];

    /**
     * Criticality score per module (0..100).
     *
     * @var array<string, int>
     */
    private const TIER_WEIGHTS = [
        'Core' => 100,
        'Banking' => 95,
        'Finance' => 95,
        'Treasury' => 90,
        'Payment' => 90,
        'Identity' => 85,
        'Hospital' => 80,
        'SupplyChain' => 75,
        'Logistics' => 70,
        'Platform' => 60,
        'Hotel' => 50,
        'Mall' => 45,
        'AutoDex' => 40,
        'Circular' => 35,
    ];

    /**
     * Analyzes blast radius of a change targeting a module.
     *
     * @param  list<string>  $changedFiles
     * @return array{target_module: string, direct_impacts: list<string>, transitive_impacts: list<string>, blast_radius_score: int, requires_multimodule_freeze: bool, affected_tiers: list<string>}
     */
    public function analyzeBlastRadius(string $targetModule, array $changedFiles = []): array
    {
        $directImpacts = self::DEPENDENCY_GRAPH[$targetModule] ?? [];
        $transitiveImpacts = [];

        foreach ($directImpacts as $dep) {
            $secondOrder = self::DEPENDENCY_GRAPH[$dep] ?? [];
            foreach ($secondOrder as $sDep) {
                if ($sDep !== $targetModule && ! in_array($sDep, $directImpacts, true) && ! in_array($sDep, $transitiveImpacts, true)) {
                    $transitiveImpacts[] = $sDep;
                }
            }
        }

        sort($directImpacts);
        sort($transitiveImpacts);

        // Calculate blast radius score
        $baseWeight = self::TIER_WEIGHTS[$targetModule] ?? 30;
        $directPenalty = count($directImpacts) * 8;
        $transitivePenalty = count($transitiveImpacts) * 4;

        // File-level modifiers
        $fileModifier = 0;
        foreach ($changedFiles as $file) {
            if (str_contains($file, 'Contracts/') || str_contains($file, 'Events/')) {
                $fileModifier += 15; // Breaking public contract interface
            } elseif (str_contains($file, 'database/migrations/')) {
                $fileModifier += 20; // Schema alter
            } elseif (str_contains($file, 'Models/')) {
                $fileModifier += 10;
            } else {
                $fileModifier += 2;
            }
        }

        $rawScore = $baseWeight + $directPenalty + $transitivePenalty + min(50, $fileModifier);
        $finalBlastScore = min(100, $rawScore);

        $affectedTiers = [];
        if ($finalBlastScore >= 80) {
            $affectedTiers[] = 'TIER_1_ENTERPRISE_CORE';
        }
        if ($finalBlastScore >= 50) {
            $affectedTiers[] = 'TIER_2_BUSINESS_OPS';
        }
        $affectedTiers[] = 'TIER_3_EDGE_SERVICES';

        return [
            'target_module' => $targetModule,
            'direct_impacts' => $directImpacts,
            'transitive_impacts' => $transitiveImpacts,
            'blast_radius_score' => $finalBlastScore,
            'requires_multimodule_freeze' => $finalBlastScore >= 75,
            'affected_tiers' => $affectedTiers,
        ];
    }
}
