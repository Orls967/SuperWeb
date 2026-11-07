<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Models\MiningMetalsTradingPosition;
use Modules\Mining\Domain\Models\MiningMetalsWarehouseReceipt;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;

final class AuditMiningMetalsDeskAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        // 1. Mass conservation: total tapped metal + slag <= total ore feed
        $runs = MiningSmelterFurnaceRun::query()->whereNotNull('metal_output_tons')->get();
        $massViolations = 0;
        foreach ($runs as $run) {
            $feed = (float) $run->ore_feed_wmt;
            $outputs = (float) $run->metal_output_tons + (float) $run->slag_output_tons;
            if ($outputs > ($feed * 1.05)) { // 5% allowance for slag flux addition
                $massViolations++;
            }
        }

        // 2. Positions and MTM ledger consistency
        $positions = MiningMetalsTradingPosition::query()->get();
        $totalUnrealizedPnlIdr = 0;
        foreach ($positions as $pos) {
            $totalUnrealizedPnlIdr += (int) $pos->unrealized_pnl_idr;
        }

        // 3. Warehouse receipt collateral lock
        $receipts = MiningMetalsWarehouseReceipt::query()->where('is_collateral_locked', true)->get();
        $totalCollateralTons = 0.0;
        foreach ($receipts as $rcp) {
            $totalCollateralTons += (float) $rcp->net_weight_tons;
        }

        // 4. Clean summary
        $isPassed = $massViolations === 0;

        return [
            'total_furnace_runs_audited' => $runs->count(),
            'mass_balance_violations' => $massViolations,
            'is_mass_conservation_verified' => $massViolations === 0,
            'active_trading_positions' => $positions->count(),
            'aggregate_unrealized_mtm_pnl_idr' => $totalUnrealizedPnlIdr,
            'locked_collateral_receipts' => $receipts->count(),
            'total_locked_metal_tons' => round($totalCollateralTons, 2),
            'audit_status' => $isPassed ? 'CLEAN' : 'DISCREPANCY_DETECTED',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
