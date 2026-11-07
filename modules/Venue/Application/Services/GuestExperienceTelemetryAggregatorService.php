<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\ZoneWpsMetric;

/**
 * Service to aggregate post-event guest sentiment, NPS, and real-time operational WPS
 * into the venue brand scorecard (Item 117.4).
 */
class GuestExperienceTelemetryAggregatorService
{
    /**
     * Compute comprehensive night scorecard for a completed or ongoing event.
     *
     * @param  array<int, array{user_id: int, score: int, comment: string|null, category: string}>  $feedbackSurveyResponses
     * @return array{
     *     venue_id: int,
     *     event_id: int,
     *     nps_score: float,
     *     promoters_count: int,
     *     passives_count: int,
     *     detractors_count: int,
     *     wps_operational_index: float,
     *     brand_health_score: float,
     *     tier_badge: string,
     *     summary_narrative: string
     * }
     */
    public function computeEventBrandScorecard(
        EntertainmentVenue $venue,
        EntertainmentEvent $event,
        array $feedbackSurveyResponses
    ): array {
        $totalResponses = count($feedbackSurveyResponses);

        $promoters = 0;
        $passives = 0;
        $detractors = 0;

        foreach ($feedbackSurveyResponses as $resp) {
            $score = (int) ($resp['score'] ?? 5);
            if ($score >= 9) {
                $promoters++;
            } elseif ($score >= 7) {
                $passives++;
            } else {
                $detractors++;
            }
        }

        // NPS = % Promoters - % Detractors (ranges from -100 to +100)
        $npsScore = 0.0;
        if ($totalResponses > 0) {
            $promoterPct = ($promoters / $totalResponses) * 100.0;
            $detractorPct = ($detractors / $totalResponses) * 100.0;
            $npsScore = round($promoterPct - $detractorPct, 1);
        }

        // Operational WPS component from database
        $avgWps = ZoneWpsMetric::where('event_id', $event->id)->avg('wps_score');
        $wpsOperationalIndex = $avgWps !== null ? round((float) $avgWps, 1) : 85.0;

        // Composite Brand Health Score (0 - 100):
        // 60% operational efficiency (WPS) + 40% NPS normalized to 0-100 scale ((NPS + 100) / 2)
        $normalizedNps = max(0.0, min(100.0, ($npsScore + 100.0) / 2.0));
        $brandHealthScore = round(($wpsOperationalIndex * 0.60) + ($normalizedNps * 0.40), 1);

        $tier = 'STANDARD';
        if ($brandHealthScore >= 90.0) {
            $tier = 'WORLD_CLASS_PLATINUM';
        } elseif ($brandHealthScore >= 75.0) {
            $tier = 'EXCELLENCE_GOLD';
        } elseif ($brandHealthScore < 50.0) {
            $tier = 'DEGRADED_ACTION_REQUIRED';
        }

        $narrative = sprintf(
            'Event %s completed with NPS of %+.1f (%d respondents) and operational WPS of %.1f. Overall brand health rated %s.',
            $event->title,
            $npsScore,
            $totalResponses,
            $wpsOperationalIndex,
            $tier
        );

        return [
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'nps_score' => $npsScore,
            'promoters_count' => $promoters,
            'passives_count' => $passives,
            'detractors_count' => $detractors,
            'wps_operational_index' => $wpsOperationalIndex,
            'brand_health_score' => $brandHealthScore,
            'tier_badge' => $tier,
            'summary_narrative' => $narrative,
        ];
    }
}
