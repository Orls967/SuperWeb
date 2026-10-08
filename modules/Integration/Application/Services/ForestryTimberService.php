<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ForestryTimberService (Fase 172 — Lini 23)
 *
 * Implements:
 *  - 172.1 Sustainable timber harvest quota enforcement (harvest <= quota)
 *  - 172.2 Chain-of-custody tickets with cryptographic hash (stump -> mill -> finished)
 *  - 172.3 Forest restoration survival evidence validation for carbon/subsidy claims
 */
class ForestryTimberService
{
    /**
     * Register concession plot and annual harvest quota.
     */
    public function registerPlot(string $plotCode, string $concessionName, float $quotaM3): object
    {
        DB::table('for_concession_plots')->updateOrInsert(
            ['plot_code' => $plotCode],
            [
                'concession_name' => $concessionName,
                'sustainable_quota_m3' => $quotaM3,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('for_concession_plots')->where('plot_code', $plotCode)->first();
    }

    /**
     * Log timber harvest ticket. Enforces harvest volume <= sustainable quota.
     */
    public function issueCustodyTicket(string $plotCode, string $stage, float $volumeM3, string $permitNum): object
    {
        $plot = DB::table('for_concession_plots')->where('plot_code', $plotCode)->first();
        if ($plot) {
            $current = (float) $plot->harvested_volume_m3;
            $maxQuota = (float) $plot->sustainable_quota_m3;
            if ($current + $volumeM3 > $maxQuota) {
                throw new \RuntimeException("Harvest quota breached: Harvest volume ({$volumeM3} m3) exceeds remaining quota.");
            }
        }

        $code = 'TCK-FOR-'.strtoupper(Str::random(8));
        $hash = hash('sha256', "{$code}:{$plotCode}:{$stage}:{$volumeM3}:{$permitNum}");

        $id = DB::table('for_custody_tickets')->insertGetId([
            'ticket_code' => $code,
            'plot_code' => $plotCode,
            'stage' => strtoupper($stage),
            'volume_m3' => $volumeM3,
            'permit_number' => $permitNum,
            'ticket_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($plot) {
            DB::table('for_concession_plots')->where('plot_code', $plotCode)->update([
                'harvested_volume_m3' => DB::raw("harvested_volume_m3 + {$volumeM3}"),
                'updated_at' => now(),
            ]);
        }

        return (object) DB::table('for_custody_tickets')->find($id);
    }

    /**
     * Record restoration polygon and verify survival rate evidence.
     */
    public function recordRestoration(string $polygonCode, float $hectares, float $survivalRatePct, bool $evidenceVerified): object
    {
        // Survival rate must be >= 75% and field evidence must be verified to be eligible for claims
        $isEligible = ($survivalRatePct >= 75.0 && $evidenceVerified);

        DB::table('for_restoration_polygons')->updateOrInsert(
            ['polygon_code' => $polygonCode],
            [
                'planted_hectares' => $hectares,
                'tree_survival_rate_pct' => $survivalRatePct,
                'field_evidence_verified' => $evidenceVerified,
                'claim_eligible' => $isEligible,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('for_restoration_polygons')->where('polygon_code', $polygonCode)->first();
    }

    /**
     * Quality audit gate (`forest:audit`).
     */
    public function audit(): array
    {
        $overHarvest = DB::table('for_concession_plots')
            ->whereRaw('harvested_volume_m3 > sustainable_quota_m3')
            ->count();

        return [
            'status' => $overHarvest === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_plots' => DB::table('for_concession_plots')->count(),
            'total_tickets' => DB::table('for_custody_tickets')->count(),
            'total_restoration_polygons' => DB::table('for_restoration_polygons')->count(),
            'discrepancy_count' => $overHarvest,
        ];
    }
}
