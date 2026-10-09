<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * CxMetricsVocOrchestrationService (Fase 250)
 *
 * Implements:
 *  - 250.1 Voice of Customer (VoC) aggregation (NPS, sentiment, themes) & closed-loop remediation
 *  - 250.2 Digital CX journey mapping & step-level drop-off instrumentation
 *  - 250.3 Touchpoint experience personalization, frequency governance & strict consent compliance
 *  - 250.4 Deterministic CX financial correlation modeling (NPS/CSAT vs spend/retention)
 *  - 250.6 Edge case: Low NPS detractor ratings automatically spawn opened cases
 *  - 250.7 Explicit "Correlation != Causation" statistical reporting requirement
 */
class CxMetricsVocOrchestrationService
{
    /**
     * Record VoC customer feedback with auto-case opening for detractors (250.1 & 250.6).
     */
    public function recordVocFeedback(
        string $customerId,
        string $businessLine,
        int $npsScore,
        string $sentiment,
        string $theme
    ): object {
        if ($npsScore < 0 || $npsScore > 10) {
            throw new InvalidArgumentException('NPS score must be between 0 and 10.');
        }

        // Edge case 250.6: Detractor score (<= 6) automatically opens closed-loop case
        $isDetractor = ($npsScore <= 6);
        $caseOpened = $isDetractor;
        $status = $isDetractor ? 'OPEN' : 'NOT_REQUIRED';

        $code = 'VOC-'.strtoupper(Str::random(8));

        $id = DB::table('cx_voc_feedback')->insertGetId([
            'feedback_code' => $code,
            'customer_id' => strtoupper($customerId),
            'business_line' => strtoupper($businessLine),
            'nps_score' => $npsScore,
            'sentiment' => strtoupper($sentiment),
            'theme' => strtoupper($theme),
            'closed_loop_case_opened' => $caseOpened,
            'closed_loop_status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('cx_voc_feedback')->find($id);
    }

    /**
     * Resolve closed-loop detractor case (250.1 & 250.5).
     */
    public function resolveClosedLoopCase(int $feedbackId, string $resolutionNotes): object
    {
        $fb = DB::table('cx_voc_feedback')->find($feedbackId);
        if (! $fb) {
            throw new InvalidArgumentException("Feedback #{$feedbackId} not found.");
        }

        DB::table('cx_voc_feedback')
            ->where('id', $feedbackId)
            ->update([
                'closed_loop_status' => 'RESOLVED',
                'updated_at' => now(),
            ]);

        return (object) DB::table('cx_voc_feedback')->find($feedbackId);
    }

    /**
     * Record digital journey step drop-off instrumentation (250.2 & 250.5).
     */
    public function recordJourneyStepMetrics(
        string $journeyCode,
        string $stepName,
        int $visitorsCount,
        int $dropOffCount
    ): object {
        $dropRate = $visitorsCount > 0 ? round(($dropOffCount / $visitorsCount) * 100.0, 2) : 0.0;

        $id = DB::table('cx_journey_steps')->insertGetId([
            'journey_code' => strtoupper($journeyCode),
            'step_name' => strtoupper($stepName),
            'visitors_count' => $visitorsCount,
            'drop_off_count' => $dropOffCount,
            'drop_off_rate_pct' => $dropRate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('cx_journey_steps')->find($id);
    }

    /**
     * Deliver personalized touchpoint interaction respecting consent and frequency caps (250.3 & 250.5).
     */
    public function deliverPersonalizedInteraction(
        string $customerId,
        string $touchpoint,
        string $messagePayload,
        bool $userConsentGranted,
        int $recentInteractionsToday = 0
    ): object {
        // Strict privacy consent guard (250.3 & 250.5)
        if (! $userConsentGranted) {
            throw new InvalidArgumentException('Personalization denied: User privacy consent not granted (250.3).');
        }

        // Frequency governance guard (max 3 per day)
        $freqCapExceeded = ($recentInteractionsToday >= 3);
        if ($freqCapExceeded) {
            throw new InvalidArgumentException('Personalization suppressed: Daily touchpoint frequency cap exceeded.');
        }

        $code = 'INT-'.strtoupper(Str::random(8));

        $id = DB::table('cx_personalization_interactions')->insertGetId([
            'interaction_code' => $code,
            'customer_id' => strtoupper($customerId),
            'touchpoint' => strtoupper($touchpoint),
            'user_consent_granted' => true,
            'frequency_cap_exceeded' => false,
            'message_payload' => $messagePayload,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('cx_personalization_interactions')->find($id);
    }

    /**
     * Calculate statistical CX financial correlation with mandatory non-causal disclaimer (250.4 & 250.7).
     */
    public function calculateFinancialCorrelation(
        string $metricPair,
        array $xValues,
        array $yValues
    ): object {
        $n = count($xValues);
        if ($n < 2 || count($yValues) !== $n) {
            throw new InvalidArgumentException('Correlation calculation requires at least 2 paired data points.');
        }

        // Pearson r formula
        $meanX = array_sum($xValues) / $n;
        $meanY = array_sum($yValues) / $n;

        $numerator = 0.0;
        $denomX = 0.0;
        $denomY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $xValues[$i] - $meanX;
            $dy = $yValues[$i] - $meanY;
            $numerator += ($dx * $dy);
            $denomX += ($dx * $dx);
            $denomY += ($dy * $dy);
        }

        $denominator = sqrt($denomX * $denomY);
        $pearsonR = $denominator > 0 ? round($numerator / $denominator, 3) : 0.0;

        $disclaimer = 'STATISTICAL NOTICE: Correlation does not imply causation. Observed association indicates linear alignment only (250.7).';
        $code = 'CORR-'.strtoupper(Str::random(8));

        $id = DB::table('cx_financial_correlation_models')->insertGetId([
            'model_code' => $code,
            'metric_pair' => strtoupper($metricPair),
            'pearson_r_coefficient' => $pearsonR,
            'is_correlation_explicitly_labeled' => true,
            'disclaimer_text' => $disclaimer,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('cx_financial_correlation_models')->find($id);
    }

    /**
     * CX & Customer Platform Audit (`crm:audit`) (250.5, 250.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Detractors (NPS <= 6) without opened closed-loop cases
        $neglectedDetractors = DB::table('cx_voc_feedback')
            ->where('nps_score', '<=', 6)
            ->where('closed_loop_case_opened', false)
            ->count();

        // Discrepancy 2: Personalization delivered without user consent
        $nonConsensualPersonalization = DB::table('cx_personalization_interactions')
            ->where('user_consent_granted', false)
            ->count();

        // Discrepancy 3: Correlation models without explicit disclaimer labeling
        $unlabeledCorrelations = DB::table('cx_financial_correlation_models')
            ->where('is_correlation_explicitly_labeled', false)
            ->count();

        $discrepancies = $neglectedDetractors + $nonConsensualPersonalization + $unlabeledCorrelations;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_feedback' => DB::table('cx_voc_feedback')->count(),
            'total_journey_steps' => DB::table('cx_journey_steps')->count(),
            'total_interactions' => DB::table('cx_personalization_interactions')->count(),
            'total_correlation_models' => DB::table('cx_financial_correlation_models')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
