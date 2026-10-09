<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * ThirdPartyRiskService (Fase 205)
 *
 * Implements:
 *  - 205.1 Vendor criticality tiering with due diligence depth
 *  - 205.3 Cross-line concentration exposure monitoring and breach locking
 *  - 205.4 Exit and continuity playbook tracking
 */
class ThirdPartyRiskService
{
    /**
     * Register vendor with criticality tier and maximum concentration threshold.
     */
    public function registerVendor(string $code, string $name, string $tier, float $maxConcentrationLimit, bool $hasExitPlaybook = true): object
    {
        DB::table('erm_vendor_criticalities')->updateOrInsert(
            ['vendor_code' => strtoupper($code)],
            [
                'vendor_name' => $name,
                'criticality_tier' => strtoupper($tier),
                'max_allowed_concentration_idr' => $maxConcentrationLimit,
                'current_aggregate_exposure_idr' => 0.00,
                'concentration_breached' => false,
                'has_exit_continuity_playbook' => $hasExitPlaybook,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('erm_vendor_criticalities')->where('vendor_code', strtoupper($code))->first();
    }

    /**
     * Update aggregated exposure from transactions across lines.
     */
    public function updateExposure(string $vendorCode, float $newExposure): object
    {
        $vendor = DB::table('erm_vendor_criticalities')->where('vendor_code', strtoupper($vendorCode))->first();
        if (! $vendor) {
            throw new \InvalidArgumentException("Vendor {$vendorCode} not found.");
        }

        $breached = ($newExposure > (float) $vendor->max_allowed_concentration_idr);

        DB::table('erm_vendor_criticalities')->where('vendor_code', strtoupper($vendorCode))->update([
            'current_aggregate_exposure_idr' => $newExposure,
            'concentration_breached' => $breached,
            'updated_at' => now(),
        ]);

        return (object) DB::table('erm_vendor_criticalities')->where('vendor_code', strtoupper($vendorCode))->first();
    }

    /**
     * Quality audit gate (`vendor:audit`).
     */
    public function audit(): array
    {
        // Tier 1 Critical vendors lacking exit playbooks
        $missingPlaybooks = DB::table('erm_vendor_criticalities')
            ->where('criticality_tier', 'TIER_1_CRITICAL')
            ->where('has_exit_continuity_playbook', false)
            ->count();

        return [
            'status' => $missingPlaybooks === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_vendors' => DB::table('erm_vendor_criticalities')->count(),
            'discrepancy_count' => $missingPlaybooks,
        ];
    }
}
