<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * InvestorRelationsService (Fase 211)
 *
 * Implements:
 *  - 211.2 KPI & non-GAAP reconciliation bridge (GAAP Operating Profit + Net Reconciling Items == Non-GAAP metric)
 *  - 211.4 Shareholder corporate actions and strictly balanced token supplies
 */
class InvestorRelationsService
{
    /**
     * Build non-GAAP bridge verifying exact formula parity.
     */
    public function recordNonGaapBridge(string $period, string $metricName, float $nongaapAmount, float $gaapAmount, float $reconcilingItems): object
    {
        $expectedNonGaap = round($gaapAmount + $reconcilingItems, 2);
        $diff = round(abs($nongaapAmount - $expectedNonGaap), 2);
        if ($diff > 0.0) {
            throw new \RuntimeException("Non-GAAP bridge discrepancy: Reported amount IDR {$nongaapAmount} does not equal GAAP ({$gaapAmount}) + reconciling items ({$reconcilingItems}).");
        }

        $code = 'BRIDGE-'.strtoupper(Str::slug($metricName)).'-'.strtoupper($period);

        DB::table('fin_ir_nongaap_bridges')->updateOrInsert(
            ['bridge_code' => $code],
            [
                'period_code' => strtoupper($period),
                'metric_name' => strtoupper($metricName),
                'reported_nongaap_amount_idr' => $nongaapAmount,
                'gaap_operating_profit_idr' => $gaapAmount,
                'reconciling_items_net_idr' => $reconcilingItems,
                'bridge_discrepancy_idr' => 0.00,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('fin_ir_nongaap_bridges')->where('bridge_code', $code)->first();
    }

    /**
     * Execute corporate action verifying exact supply conservation.
     */
    public function executeCorporateAction(string $actionType, float $preSupply, float $deltaCreated, float $postSupply): object
    {
        $expectedPost = round($preSupply + $deltaCreated, 4);
        $diff = round(abs($postSupply - $expectedPost), 4);
        if ($diff > 0.0) {
            throw new \RuntimeException("Corporate action imbalance: Pre ({$preSupply}) + Delta ({$deltaCreated}) != Post ({$postSupply}).");
        }

        $code = 'CA-'.strtoupper(Str::random(8));

        $id = DB::table('fin_ir_corporate_actions')->insertGetId([
            'action_code' => $code,
            'action_type' => strtoupper($actionType),
            'pre_action_token_supply' => $preSupply,
            'delta_tokens_created' => $deltaCreated,
            'post_action_token_supply' => $postSupply,
            'balance_check_variance' => 0.0000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('fin_ir_corporate_actions')->find($id);
    }

    /**
     * Quality audit gate (`group:audit`).
     */
    public function audit(): array
    {
        $bridgeDiscrepancies = DB::table('fin_ir_nongaap_bridges')
            ->where('bridge_discrepancy_idr', '!=', 0.0)
            ->count();

        $actionDiscrepancies = DB::table('fin_ir_corporate_actions')
            ->where('balance_check_variance', '!=', 0.0)
            ->count();

        $discrepancies = $bridgeDiscrepancies + $actionDiscrepancies;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_bridges' => DB::table('fin_ir_nongaap_bridges')->count(),
            'total_corporate_actions' => DB::table('fin_ir_corporate_actions')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
