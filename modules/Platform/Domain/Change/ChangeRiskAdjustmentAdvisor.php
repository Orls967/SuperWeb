<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use Modules\Platform\Domain\Enums\ChangeRiskTier;
use Modules\Platform\Domain\Models\ChangeRequest;

final class ChangeRiskAdjustmentAdvisor
{
    /**
     * @param  array<string, mixed>  $historicalContext
     * @return array{
     *     recommended_risk_tier: ChangeRiskTier,
     *     tier_adjusted: bool,
     *     adjustment_reasons: list<string>,
     *     mitigation_suggestions: list<string>
     * }
     */
    public function adviseRiskAdjustment(ChangeRequest $changeRequest, array $historicalContext = []): array
    {
        $currentTier = $changeRequest->risk_tier;
        $adjustedTier = $currentTier;
        $reasons = [];
        $suggestions = [];

        $recentIncidentsCount = (int) ($historicalContext['recent_incidents_count'] ?? 0);
        $authorChangesCount = (int) ($historicalContext['author_changes_count'] ?? 10);
        $hasDataMigration = (bool) ($historicalContext['has_data_migration'] ?? false);

        if ($recentIncidentsCount > 2 && $currentTier === ChangeRiskTier::Low) {
            $adjustedTier = ChangeRiskTier::Medium;
            $reasons[] = "Recent production incidents in subsystem ({$recentIncidentsCount}) warrant risk tier elevation from low to medium";
            $suggestions[] = 'Perform synthetic smoke testing on staging before initiating canary deployment';
        }

        if ($hasDataMigration && ($currentTier === ChangeRiskTier::Low || $currentTier === ChangeRiskTier::Medium)) {
            $adjustedTier = ChangeRiskTier::High;
            $reasons[] = 'Change contains structural or bulk data migration; elevated to high risk';
            $suggestions[] = 'Verify idempotent chunking and dry-run backfill on sanitized database clone';
        }

        if ($authorChangesCount < 3) {
            $suggestions[] = 'Assign a senior peer reviewer for CAB review due to new contributor profile';
        }

        return [
            'recommended_risk_tier' => $adjustedTier,
            'tier_adjusted' => $adjustedTier !== $currentTier,
            'adjustment_reasons' => $reasons,
            'mitigation_suggestions' => $suggestions,
        ];
    }
}
