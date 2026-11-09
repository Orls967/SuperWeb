<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use Modules\Platform\Domain\Enums\ChangeRiskTier;
use Modules\Platform\Domain\Models\ChangeRequest;

final class ChangeImpactRadiusAnalyzer
{
    /**
     * Known subsystem dependencies map
     *
     * @var array<string, list<string>>
     */
    private const DEPENDENCY_GRAPH = [
        'core' => ['auth', 'tenant', 'database'],
        'billing' => ['core', 'payment_gateway', 'database'],
        'platform' => ['core', 'cache', 'queue', 'database'],
        'reporting' => ['database', 'core'],
        'notifications' => ['queue', 'core'],
    ];

    /**
     * @return array{
     *     subsystems_impacted: list<string>,
     *     blast_radius_tier: string,
     *     peak_traffic_overlap: bool,
     *     requires_cab_quorum: bool,
     *     recommended_deployment_window: string
     * }
     */
    public function analyzeRadius(ChangeRequest $changeRequest): array
    {
        $metadata = $changeRequest->metadata ?? [];
        $primarySubsystem = strtolower((string) ($metadata['subsystem'] ?? 'core'));

        $impacted = [$primarySubsystem];
        if (isset(self::DEPENDENCY_GRAPH[$primarySubsystem])) {
            foreach (self::DEPENDENCY_GRAPH[$primarySubsystem] as $dep) {
                if (! in_array($dep, $impacted, true)) {
                    $impacted[] = $dep;
                }
            }
        }

        $impactCount = count($impacted);
        $radiusTier = match (true) {
            $impactCount >= 4 => 'broad',
            $impactCount >= 2 => 'moderate',
            default => 'isolated',
        };

        $isCritical = $changeRequest->risk_tier === ChangeRiskTier::Critical;
        $isHigh = $changeRequest->risk_tier === ChangeRiskTier::High;

        $requiresCab = $isCritical || ($isHigh && $radiusTier === 'broad');

        $window = match ($radiusTier) {
            'broad' => 'weekend_offpeak_02_04_utc',
            'moderate' => 'weekday_offpeak_22_02_utc',
            default => 'business_hours_safe',
        };

        return [
            'subsystems_impacted' => $impacted,
            'blast_radius_tier' => $radiusTier,
            'peak_traffic_overlap' => $radiusTier !== 'isolated',
            'requires_cab_quorum' => $requiresCab,
            'recommended_deployment_window' => $window,
        ];
    }
}
