<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Release;

use Carbon\CarbonImmutable;
use Modules\Platform\Domain\Enums\ChangeRiskTier;
use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Enums\ReleaseTrainStatus;
use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\DataBackfill;
use Modules\Platform\Domain\Models\ReleaseTrain;

final class ReleaseHealthDashboardService
{
    /**
     * @return array{
     *     active_trains_count: int,
     *     pending_changes_count: int,
     *     high_risk_changes_count: int,
     *     active_deployments_count: int,
     *     running_backfills_count: int,
     *     trains: list<array<string, mixed>>,
     *     summary_status: string
     * }
     */
    public function getExecutiveReleaseSummary(): array
    {
        $activeTrains = ReleaseTrain::query()
            ->whereIn('status', [ReleaseTrainStatus::Open->value, ReleaseTrainStatus::Freezing->value, ReleaseTrainStatus::Frozen->value, ReleaseTrainStatus::Deploying->value])
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingChanges = ChangeRequest::query()
            ->whereIn('status', [ChangeStatus::Draft, ChangeStatus::UnderReview, ChangeStatus::CabReview, ChangeStatus::Scheduled])
            ->count();

        $highRiskChanges = ChangeRequest::query()
            ->whereIn('status', [ChangeStatus::Draft, ChangeStatus::UnderReview, ChangeStatus::CabReview, ChangeStatus::Scheduled])
            ->whereIn('risk_tier', [ChangeRiskTier::High, ChangeRiskTier::Critical])
            ->count();

        $activeDeployments = ChangeDeployment::query()
            ->whereIn('status', ['deploying', 'canary_evaluating', 'promoting'])
            ->count();

        $runningBackfills = DataBackfill::query()
            ->where('status', 'running')
            ->count();

        $trainsSummary = [];
        foreach ($activeTrains as $train) {
            $trainsSummary[] = [
                'id' => $train->id,
                'name' => $train->train_name,
                'version' => $train->version,
                'status' => $train->status->value,
                'changes_count' => $train->changeRequests()->count(),
                'hotfixes_count' => $train->hotfixes()->count(),
                'scheduled_departure' => $train->scheduled_departure_at?->toIso8601String(),
                'is_frozen' => $train->isFrozen(),
            ];
        }

        $summaryStatus = 'healthy';
        if ($highRiskChanges > 5 || $runningBackfills > 3) {
            $summaryStatus = 'warning';
        }
        if ($activeDeployments > 2) {
            $summaryStatus = 'congested';
        }

        return [
            'active_trains_count' => $activeTrains->count(),
            'pending_changes_count' => $pendingChanges,
            'high_risk_changes_count' => $highRiskChanges,
            'active_deployments_count' => $activeDeployments,
            'running_backfills_count' => $runningBackfills,
            'trains' => $trainsSummary,
            'summary_status' => $summaryStatus,
        ];
    }

    /**
     * @return array{
     *     mean_lead_time_minutes: float,
     *     p95_lead_time_minutes: float,
     *     change_success_rate_percent: float,
     *     emergency_change_ratio_percent: float,
     *     risk_tier_breakdown: array<string, int>
     * }
     */
    public function calculateOperationalReleaseScorecard(int $days = 30): array
    {
        $since = CarbonImmutable::now()->subDays($days);

        $changes = ChangeRequest::query()
            ->where('created_at', '>=', $since)
            ->get();

        $total = $changes->count();
        if ($total === 0) {
            return [
                'mean_lead_time_minutes' => 0.0,
                'p95_lead_time_minutes' => 0.0,
                'change_success_rate_percent' => 100.0,
                'emergency_change_ratio_percent' => 0.0,
                'risk_tier_breakdown' => [
                    'low' => 0,
                    'medium' => 0,
                    'high' => 0,
                    'critical' => 0,
                ],
            ];
        }

        $leadTimes = [];
        $successful = 0;
        $emergency = 0;
        $tierBreakdown = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'critical' => 0,
        ];

        foreach ($changes as $cr) {
            $tierBreakdown[$cr->risk_tier->value] = ($tierBreakdown[$cr->risk_tier->value] ?? 0) + 1;

            if ($cr->type->value === 'emergency') {
                $emergency++;
            }

            if ($cr->status === ChangeStatus::Completed) {
                $successful++;
                if ($cr->created_at && $cr->closed_at) {
                    $leadTimes[] = $cr->created_at->diffInMinutes($cr->closed_at);
                }
            }
        }

        $meanLeadTime = 0.0;
        $p95LeadTime = 0.0;
        if (! empty($leadTimes)) {
            sort($leadTimes);
            $meanLeadTime = array_sum($leadTimes) / count($leadTimes);
            $idx = (int) ceil(count($leadTimes) * 0.95) - 1;
            $p95LeadTime = (float) $leadTimes[max(0, $idx)];
        }

        $successRate = round(($successful / $total) * 100, 2);
        $emergencyRatio = round(($emergency / $total) * 100, 2);

        return [
            'mean_lead_time_minutes' => round($meanLeadTime, 2),
            'p95_lead_time_minutes' => round($p95LeadTime, 2),
            'change_success_rate_percent' => $successRate,
            'emergency_change_ratio_percent' => $emergencyRatio,
            'risk_tier_breakdown' => $tierBreakdown,
        ];
    }
}
