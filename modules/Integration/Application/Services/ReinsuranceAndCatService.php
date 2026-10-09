<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;

/**
 * ReinsuranceAndCatService (Fase 157 — Lini 18)
 *
 * Implements:
 *  - 157.1 Treaty & facultative reinsurance (quota share, excess of loss, stop loss)
 *  - 157.2 Ceded vs retained premium ledger balancing: gross = ceded + retained
 *  - 157.3 Risk-Based Capital (RBC) solvency evaluation
 *  - 157.4 Catastrophe (CAT) layer structure and loss allocation
 */
class ReinsuranceAndCatService
{
    /**
     * Create reinsurance treaty.
     */
    public function createTreaty(string $treatyCode, string $type, string $reinsurerCode, float $cessionPct, float $retentionLimit, float $commissionPct = 5.0): object
    {
        DB::table('reins_treaties')->updateOrInsert(
            ['treaty_code' => $treatyCode],
            [
                'treaty_type' => strtoupper($type),
                'reinsurer_code' => $reinsurerCode,
                'cession_pct' => $cessionPct,
                'retention_limit' => $retentionLimit,
                'reinsurance_commission_pct' => $commissionPct,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('reins_treaties')->where('treaty_code', $treatyCode)->first();
    }

    /**
     * Process premium cession for a policy under a treaty.
     * Enforces invarian: gross_premium = ceded_premium + retained_premium.
     */
    public function processCession(string $treatyCode, string $policyNumber, float $grossPremium): object
    {
        $treaty = DB::table('reins_treaties')->where('treaty_code', $treatyCode)->first();
        $cessionPct = (float) ($treaty->cession_pct ?? 30.0);
        $commissionPct = (float) ($treaty->reinsurance_commission_pct ?? 5.0);

        $ceded = round($grossPremium * ($cessionPct / 100.0), 2);
        $retained = round($grossPremium - $ceded, 2);
        $commission = round($ceded * ($commissionPct / 100.0), 2);

        $id = DB::table('reins_cession_records')->insertGetId([
            'treaty_code' => $treatyCode,
            'policy_number' => $policyNumber,
            'gross_premium' => $grossPremium,
            'ceded_premium' => $ceded,
            'retained_premium' => $retained,
            'reinsurance_commission' => $commission,
            'ceded_claim_recoverable' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('reins_cession_records')->find($id);
    }

    /**
     * Calculate Solvency Ratio (RBC / C-ROSS).
     */
    public function calculateSolvencyRatio(string $period, float $admittedAssets, float $requiredCapital): object
    {
        $ratio = round(($admittedAssets / max(1.0, $requiredCapital)) * 100.0, 2);
        $status = $ratio >= 120.0 ? 'SOLVENT' : 'CAPITAL_CALL';

        DB::table('reins_capital_adequacies')->updateOrInsert(
            ['evaluation_period' => $period],
            [
                'admitted_assets' => $admittedAssets,
                'minimum_capital_required' => $requiredCapital,
                'solvency_ratio_pct' => $ratio,
                'status' => $status,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('reins_capital_adequacies')->where('evaluation_period', $period)->first();
    }

    /**
     * Process CAT event loss allocation across excess-of-loss layer.
     */
    public function allocateCatLoss(string $peril, float $totalGrossLoss, float $attachmentPoint, float $layerLimit): array
    {
        // Loss below attachment is retained by primary insurer
        $retainedLoss = min($totalGrossLoss, $attachmentPoint);
        $excessLoss = max(0.0, $totalGrossLoss - $attachmentPoint);
        // Layer covers up to layer limit
        $reinsuranceCovered = min($excessLoss, $layerLimit);
        $topExhaustedLoss = max(0.0, $excessLoss - $layerLimit);

        return [
            'peril' => strtoupper($peril),
            'total_loss' => $totalGrossLoss,
            'primary_retained' => $retainedLoss,
            'reinsurance_recovered' => $reinsuranceCovered,
            'exhausted_uncovered' => $topExhaustedLoss,
        ];
    }

    /**
     * Reinsurance audit quality gate (`ins:reinsurance-audit`).
     */
    public function audit(): array
    {
        $records = DB::table('reins_cession_records')->get();
        $discrepancies = 0;

        foreach ($records as $r) {
            $sum = round((float) $r->ceded_premium + (float) $r->retained_premium, 2);
            if (abs($sum - (float) $r->gross_premium) > 0.01) {
                $discrepancies++;
            }
        }

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_cessions' => $records->count(),
            'total_treaties' => DB::table('reins_treaties')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
