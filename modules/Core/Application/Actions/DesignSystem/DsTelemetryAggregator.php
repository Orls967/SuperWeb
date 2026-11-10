<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\DesignSystem;

use Modules\Core\Domain\Models\DsComponent;
use Modules\Core\Domain\Models\DsComponentUsage;
use Modules\Core\Domain\Models\DsToken;
use Modules\Core\Domain\Models\UxFinding;

/**
 * Agregator metrik telemetri, tingkat adopsi komponen, dan kesehatan design system.
 */
class DsTelemetryAggregator
{
    /**
     * Hitung ringkasan komprehensif telemetri design system.
     *
     * @return array{adoption_rate_pct: float, total_components: int, released_components: int, deprecated_components: int, total_usages_detected: int, top_used_components: array<int, array<string, mixed>>, deprecated_usages: array<int, array<string, mixed>>, token_counts_by_theme: array<string, int>, health_score_pct: float}
     */
    public function aggregate(): array
    {
        $allComponents = DsComponent::all();
        $totalComponents = $allComponents->count();

        $releasedCount = 0;
        $deprecatedCount = 0;
        $deprecatedNames = [];

        foreach ($allComponents as $comp) {
            if (in_array($comp->status, ['released', 'lts'], true)) {
                $releasedCount++;
            } elseif ($comp->status === 'deprecated') {
                $deprecatedCount++;
                $deprecatedNames[] = $comp->name;
            }
        }

        // Hitung penggunaan dari tabel core_ds_component_usage
        $usages = DsComponentUsage::all();
        $totalUsages = 0;
        $usageByComponent = [];
        $deprecatedUsages = [];

        foreach ($usages as $usage) {
            $compName = $usage->component_name;
            $count = (int) $usage->usage_count;
            $totalUsages += $count;

            $usageByComponent[$compName] = ($usageByComponent[$compName] ?? 0) + $count;

            if (in_array($compName, $deprecatedNames, true)) {
                $deprecatedUsages[] = [
                    'component' => $compName,
                    'view_path' => $usage->view_path,
                    'count' => $count,
                ];
            }
        }

        arsort($usageByComponent);
        $topUsed = [];
        $rank = 1;
        foreach (array_slice($usageByComponent, 0, 10, true) as $name => $count) {
            $topUsed[] = [
                'rank' => $rank++,
                'name' => $name,
                'usage_count' => $count,
            ];
        }

        // Hitung token berdasarkan tema
        $tokens = DsToken::all();
        $tokenCounts = ['light' => 0, 'dark' => 0, 'high-contrast' => 0];
        foreach ($tokens as $token) {
            if (isset($tokenCounts[$token->theme])) {
                $tokenCounts[$token->theme]++;
            }
        }

        // Tingkat adopsi rilis (% komponen aktif yang berstatus released atau lts)
        $adoptionRate = $totalComponents > 0
            ? round(($releasedCount / $totalComponents) * 100, 1)
            : 0.0;

        // Skor kesehatan design system (dikurangi penalti jika ada deprecated usage atau finding kritis)
        $unresolvedFindings = UxFinding::where('status', 'open')->count();
        $penalty = (count($deprecatedUsages) * 2) + ($unresolvedFindings * 3);
        $healthScore = max(0.0, min(100.0, round(95.0 - $penalty, 1)));

        return [
            'adoption_rate_pct' => $adoptionRate,
            'total_components' => $totalComponents,
            'released_components' => $releasedCount,
            'deprecated_components' => $deprecatedCount,
            'total_usages_detected' => $totalUsages,
            'top_used_components' => $topUsed,
            'deprecated_usages' => $deprecatedUsages,
            'token_counts_by_theme' => $tokenCounts,
            'health_score_pct' => $healthScore,
        ];
    }
}

