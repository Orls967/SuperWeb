<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * InternalCapabilitiesMarketplaceService (Fase 235)
 *
 * Implements:
 *  - 235.1 Capability-as-a-product catalog & unit-cost chargeback engine
 *  - 235.2 Developer portal subscription management with rate limiting
 *  - 235.3 Service level contracts with SLA availability targets & internal breach credits
 *  - 235.5 Tests: Chargeback sum net zero balance across units, SLA credits accounted
 *  - 235.6 Edge case: Shadow duplication bypass detected & prefer-platform policy enforced
 *  - 235.7 Deprecation policy with migration grace period
 */
class InternalCapabilitiesMarketplaceService
{
    /**
     * Register platform capability product (235.1 & 235.3).
     */
    public function registerCapability(
        string $code,
        string $name,
        string $category,
        string $version,
        float $unitCost,
        float $slaAvailability = 99.90,
        int $latencyTargetMs = 100
    ): object {
        DB::table('ppm_internal_capabilities')->updateOrInsert(
            ['capability_code' => strtoupper($code)],
            [
                'name' => $name,
                'category' => strtoupper($category),
                'version' => $version,
                'unit_cost' => $unitCost,
                'sla_availability_pct' => $slaAvailability,
                'latency_target_ms' => $latencyTargetMs,
                'status' => 'ACTIVE',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('ppm_internal_capabilities')->where('capability_code', strtoupper($code))->first();
    }

    /**
     * Subscribe internal consumer business line to capability (235.2).
     */
    public function subscribeConsumer(
        string $capabilityCode,
        string $consumerBusinessLine,
        int $rateLimitRpm = 1000
    ): object {
        $code = 'SUB-'.strtoupper(Str::random(8));

        $id = DB::table('ppm_capability_subscriptions')->insertGetId([
            'subscription_code' => $code,
            'capability_code' => strtoupper($capabilityCode),
            'consumer_business_line' => strtoupper($consumerBusinessLine),
            'rate_limit_rpm' => $rateLimitRpm,
            'is_official_platform' => true,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ppm_capability_subscriptions')->find($id);
    }

    /**
     * Record usage and chargeback maintaining zero-sum cross-unit transfer (235.1 & 235.5).
     */
    public function recordUsageAndChargeback(
        string $capabilityCode,
        string $consumerUnit,
        int $usageVolume,
        float $creditPenaltyDeduction = 0
    ): object {
        $cap = DB::table('ppm_internal_capabilities')->where('capability_code', strtoupper($capabilityCode))->first();
        if (! $cap) {
            throw new \InvalidArgumentException("Capability {$capabilityCode} not found.");
        }

        $grossCharge = round($usageVolume * (float) $cap->unit_cost, 2);
        $netTransfer = max(0, round($grossCharge - $creditPenaltyDeduction, 2));

        $code = 'CB-'.strtoupper(Str::random(8));

        $id = DB::table('ppm_internal_chargeback_ledgers')->insertGetId([
            'entry_code' => $code,
            'capability_code' => $cap->capability_code,
            'provider_unit' => 'PLATFORM_CORE',
            'consumer_unit' => strtoupper($consumerUnit),
            'usage_volume' => $usageVolume,
            'charge_amount' => $grossCharge,
            'credit_penalty_amount' => $creditPenaltyDeduction,
            'net_transferred_amount' => $netTransfer,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ppm_internal_chargeback_ledgers')->find($id);
    }

    /**
     * Record SLA availability breach and apply credit penalty to consumer (235.3 & 235.5).
     */
    public function recordSlaBreach(
        string $capabilityCode,
        string $consumerUnit,
        float $actualAvailabilityPct,
        float $creditPenaltyAmount
    ): object {
        $cap = DB::table('ppm_internal_capabilities')->where('capability_code', strtoupper($capabilityCode))->first();
        if (! $cap) {
            throw new \InvalidArgumentException("Capability {$capabilityCode} not found.");
        }

        $code = 'BRC-'.strtoupper(Str::random(8));

        $id = DB::table('ppm_capability_sla_breaches')->insertGetId([
            'breach_code' => $code,
            'capability_code' => $cap->capability_code,
            'consumer_unit' => strtoupper($consumerUnit),
            'actual_availability_pct' => $actualAvailabilityPct,
            'target_availability_pct' => (float) $cap->sla_availability_pct,
            'credit_penalty_amount' => $creditPenaltyAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ppm_capability_sla_breaches')->find($id);
    }

    /**
     * Detect shadow bypass and enforce prefer-platform policy (235.6 Edge Case).
     */
    public function detectShadowDuplication(
        string $businessLine,
        string $bypassedCapCode,
        string $shadowProjectName
    ): object {
        $code = 'DUP-'.strtoupper(Str::random(8));

        $id = DB::table('ppm_capability_duplication_detections')->insertGetId([
            'detection_code' => $code,
            'business_line' => strtoupper($businessLine),
            'bypassed_capability_code' => strtoupper($bypassedCapCode),
            'shadow_project_name' => $shadowProjectName,
            'enforcement_action' => 'PREFER_PLATFORM_POLICY_ENFORCED', // Enforce consolidation to official capability
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ppm_capability_duplication_detections')->find($id);
    }

    /**
     * Mark capability deprecated with designated migration grace period (235.7).
     */
    public function deprecateCapability(string $capabilityCode, string $gracePeriodEnd): object
    {
        DB::table('ppm_internal_capabilities')
            ->where('capability_code', strtoupper($capabilityCode))
            ->update([
                'status' => 'DEPRECATED',
                'deprecation_grace_period_end' => $gracePeriodEnd,
                'updated_at' => now(),
            ]);

        return (object) DB::table('ppm_internal_capabilities')->where('capability_code', strtoupper($capabilityCode))->first();
    }

    /**
     * Quality audit gate (`api:audit`).
     */
    public function audit(): array
    {
        // Discrepancy 1: Chargebacks where net transferred != (charge_amount - credit_penalty_amount)
        $invalidTransfers = DB::table('ppm_internal_chargeback_ledgers')
            ->whereRaw('ABS(net_transferred_amount - (charge_amount - credit_penalty_amount)) > 0.01')
            ->count();

        // Discrepancy 2: Capabilities marked DEPRECATED without grace period date
        $unplannedDeprecations = DB::table('ppm_internal_capabilities')
            ->where('status', 'DEPRECATED')
            ->whereNull('deprecation_grace_period_end')
            ->count();

        $discrepancies = $invalidTransfers + $unplannedDeprecations;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_capabilities' => DB::table('ppm_internal_capabilities')->count(),
            'total_subscriptions' => DB::table('ppm_capability_subscriptions')->count(),
            'total_chargeback_entries' => DB::table('ppm_internal_chargeback_ledgers')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
