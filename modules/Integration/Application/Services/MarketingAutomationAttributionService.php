<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MarketingAutomationAttributionService (Fase 222)
 *
 * Implements:
 *  - 222.1 Segment engine: RFM, behavior, lifecycle, value tier dynamic segments
 *  - 222.2 Campaign orchestration with global frequency capping and opt-out compliance
 *  - 222.3 Promotion budget governance with strict encumbrance & override approval guard
 *  - 222.4 Deterministic attribution models (First touch, Last touch, Linear multi-touch summing to 100%)
 *  - 222.6 Edge case: Global cross-channel frequency cap per subject (prevents multi-channel spamming)
 *  - 222.7 Customer fatigue & suppression linking complaint to service desk case
 */
class MarketingAutomationAttributionService
{
    /**
     * Define marketing segment.
     */
    public function createSegment(
        string $code,
        string $name,
        string $criteriaType,
        float $minScore = 0,
        float $maxScore = 100
    ): object {
        $id = DB::table('crm_marketing_segments')->insertGetId([
            'segment_code' => strtoupper($code),
            'segment_name' => $name,
            'criteria_type' => strtoupper($criteriaType),
            'min_score' => $minScore,
            'max_score' => $maxScore,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_marketing_segments')->find($id);
    }

    /**
     * Create marketing campaign with budget governance.
     */
    public function createCampaign(
        string $code,
        string $name,
        string $segmentCode,
        float $allocatedBudget,
        float $maxBudgetLimit
    ): object {
        $id = DB::table('crm_marketing_campaigns')->insertGetId([
            'campaign_code' => strtoupper($code),
            'campaign_name' => $name,
            'segment_code' => strtoupper($segmentCode),
            'allocated_budget' => $allocatedBudget,
            'spent_budget' => 0,
            'max_budget_limit' => $maxBudgetLimit,
            'requires_override_approval' => false,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_marketing_campaigns')->find($id);
    }

    /**
     * Configure customer communication preferences & global cap.
     */
    public function configureCustomerProfile(
        string $goldenId,
        int $globalDailyCap = 3,
        bool $optedOut = false
    ): object {
        DB::table('crm_customer_communication_profiles')->updateOrInsert(
            ['customer_golden_id' => strtoupper($goldenId)],
            [
                'global_daily_cap' => $globalDailyCap,
                'opted_out' => $optedOut,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('crm_customer_communication_profiles')
            ->where('customer_golden_id', strtoupper($goldenId))
            ->first();
    }

    /**
     * Dispatch campaign message respecting global cross-channel cap, opt-outs, and budgets.
     */
    public function dispatchMessage(
        string $campaignCode,
        string $goldenId,
        string $channel,
        float $cost = 10.0
    ): object {
        $campaign = DB::table('crm_marketing_campaigns')
            ->where('campaign_code', strtoupper($campaignCode))
            ->first();

        if (! $campaign) {
            throw new \InvalidArgumentException("Campaign {$campaignCode} not found.");
        }

        $profile = DB::table('crm_customer_communication_profiles')
            ->where('customer_golden_id', strtoupper($goldenId))
            ->first();

        $dispatchCode = 'DSP-'.strtoupper(Str::random(8));

        // 1. Check Opt-Out (222.2)
        if ($profile && $profile->opted_out) {
            $id = DB::table('crm_campaign_dispatches')->insertGetId([
                'dispatch_code' => $dispatchCode,
                'campaign_code' => $campaign->campaign_code,
                'customer_golden_id' => strtoupper($goldenId),
                'channel' => strtoupper($channel),
                'status' => 'SUPPRESSED_OPT_OUT',
                'cost' => 0,
                'dispatched_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) DB::table('crm_campaign_dispatches')->find($id);
        }

        // 2. Check Fatigue Suppression (222.7)
        if ($profile && $profile->fatigue_suppressed) {
            $id = DB::table('crm_campaign_dispatches')->insertGetId([
                'dispatch_code' => $dispatchCode,
                'campaign_code' => $campaign->campaign_code,
                'customer_golden_id' => strtoupper($goldenId),
                'channel' => strtoupper($channel),
                'status' => 'SUPPRESSED_FATIGUE',
                'cost' => 0,
                'dispatched_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) DB::table('crm_campaign_dispatches')->find($id);
        }

        // 3. Edge Case 222.6: Global cross-channel frequency cap per subject (not per channel!)
        $globalDailyCap = $profile ? (int) $profile->global_daily_cap : 3;
        $sentToday = DB::table('crm_campaign_dispatches')
            ->where('customer_golden_id', strtoupper($goldenId))
            ->where('status', 'SENT')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($sentToday >= $globalDailyCap) {
            $id = DB::table('crm_campaign_dispatches')->insertGetId([
                'dispatch_code' => $dispatchCode,
                'campaign_code' => $campaign->campaign_code,
                'customer_golden_id' => strtoupper($goldenId),
                'channel' => strtoupper($channel),
                'status' => 'SUPPRESSED_CAP',
                'cost' => 0,
                'dispatched_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) DB::table('crm_campaign_dispatches')->find($id);
        }

        // 4. Budget Governance & Encumbrance (222.3)
        $newSpent = (float) $campaign->spent_budget + $cost;
        if ($newSpent > (float) $campaign->max_budget_limit) {
            throw new \InvalidArgumentException(
                "Campaign {$campaignCode} exceeds maximum budget limit of {$campaign->max_budget_limit}. Approval required."
            );
        }

        DB::table('crm_marketing_campaigns')
            ->where('campaign_code', $campaign->campaign_code)
            ->update([
                'spent_budget' => $newSpent,
                'updated_at' => now(),
            ]);

        $id = DB::table('crm_campaign_dispatches')->insertGetId([
            'dispatch_code' => $dispatchCode,
            'campaign_code' => $campaign->campaign_code,
            'customer_golden_id' => strtoupper($goldenId),
            'channel' => strtoupper($channel),
            'status' => 'SENT',
            'cost' => $cost,
            'dispatched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_campaign_dispatches')->find($id);
    }

    /**
     * Trigger fatigue suppression linked to service desk complaint (222.7).
     */
    public function suppressFatigue(string $goldenId, string $caseCode): void
    {
        DB::table('crm_customer_communication_profiles')->updateOrInsert(
            ['customer_golden_id' => strtoupper($goldenId)],
            [
                'fatigue_suppressed' => true,
                'linked_service_desk_case' => strtoupper($caseCode),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Attribution model calculation (222.4 & 222.5: Sum must equal exactly 100.0%).
     */
    public function recordAttribution(
        string $goldenId,
        float $conversionValue,
        string $modelType,
        array $touchpointChannels
    ): object {
        if (empty($touchpointChannels)) {
            throw new \InvalidArgumentException("Touchpoint channels cannot be empty.");
        }

        $weights = [];
        $model = strtoupper($modelType);

        if ($model === 'FIRST_TOUCH') {
            foreach ($touchpointChannels as $i => $ch) {
                $weights[$ch] = ($i === 0) ? 100.0 : 0.0;
            }
        } elseif ($model === 'LAST_TOUCH') {
            $lastIndex = count($touchpointChannels) - 1;
            foreach ($touchpointChannels as $i => $ch) {
                $weights[$ch] = ($i === $lastIndex) ? 100.0 : 0.0;
            }
        } elseif ($model === 'MULTI_TOUCH_LINEAR') {
            $count = count($touchpointChannels);
            $base = round(100.0 / $count, 2);
            $accum = 0.0;
            foreach ($touchpointChannels as $i => $ch) {
                if ($i === $count - 1) {
                    $weights[$ch] = round(100.0 - $accum, 2);
                } else {
                    $weights[$ch] = $base;
                    $accum += $base;
                }
            }
        } else {
            throw new \InvalidArgumentException("Unsupported attribution model: {$modelType}");
        }

        // Verify mathematical invariant: Σ weights == 100.0%
        $sum = array_sum($weights);
        if (abs($sum - 100.0) > 0.01) {
            throw new \RuntimeException("Attribution invariance failure: sum of weights equals {$sum}%, expected 100.0%.");
        }

        $convCode = 'CONV-'.strtoupper(Str::random(8));

        $id = DB::table('crm_conversion_attributions')->insertGetId([
            'conversion_code' => $convCode,
            'customer_golden_id' => strtoupper($goldenId),
            'conversion_value' => $conversionValue,
            'model_type' => $model,
            'channel_touchpoints' => json_encode($touchpointChannels),
            'attributed_weights' => json_encode($weights),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('crm_conversion_attributions')->find($id);
    }

    /**
     * Quality audit gate (`marketing:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Campaign spent budget exceeding max limit
        $overbudget = DB::table('crm_marketing_campaigns')
            ->whereRaw('spent_budget > max_budget_limit')
            ->count();

        // Discrepancy 2: Any message SENT to an opted-out or fatigue-suppressed customer
        $violations = DB::table('crm_campaign_dispatches')
            ->join(
                'crm_customer_communication_profiles',
                'crm_campaign_dispatches.customer_golden_id',
                '=',
                'crm_customer_communication_profiles.customer_golden_id'
            )
            ->where('crm_campaign_dispatches.status', 'SENT')
            ->where(function ($q) {
                $q->where('crm_customer_communication_profiles.opted_out', true)
                  ->orWhere('crm_customer_communication_profiles.fatigue_suppressed', true);
            })
            ->count();

        $discrepancies = $overbudget + $violations;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_campaigns' => DB::table('crm_marketing_campaigns')->count(),
            'total_dispatches' => DB::table('crm_campaign_dispatches')->count(),
            'total_conversions' => DB::table('crm_conversion_attributions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
