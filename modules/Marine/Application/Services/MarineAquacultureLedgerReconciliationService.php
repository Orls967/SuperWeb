<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use Modules\Marine\Domain\Models\AquacultureExportLot;
use Modules\Marine\Domain\Models\AquacultureExportOrder;
use Modules\Marine\Domain\Models\AquacultureHarvestOperation;
use Modules\Marine\Domain\Models\MarineAquacultureLedgerEntry;

/**
 * Service rekonsiliasi akuntansi dan integritas keuangan akuakultur ekspor (Fase 171).
 */
class MarineAquacultureLedgerReconciliationService
{
    /**
     * Rekonsiliasi nilai ekspor terdaftar pada pesanan terhadap total kredit ledger.
     *
     * @return array{
     *     total_orders_evaluated: int,
     *     total_contract_value_minor_cents: int,
     *     total_ledger_credits_minor_cents: int,
     *     discrepancy_amount_minor_cents: int,
     *     is_fully_reconciled: bool,
     *     audit_verdict: string
     * }
     */
    public function reconcileOrderLedgerEntries(): array
    {
        $orders = AquacultureExportOrder::all();
        $totalContractValue = (int) $orders->sum('total_contract_value_minor_cents');

        $ledgerEntries = MarineAquacultureLedgerEntry::where('account_code', '1150-AQUACULTURE-INVENTORY')
            ->where('entry_type', 'CREDIT')
            ->get();

        $totalLedgerCredits = (int) $ledgerEntries->sum('amount_minor_cents');
        $discrepancy = abs($totalContractValue - $totalLedgerCredits);

        $isReconciled = ($discrepancy === 0);

        return [
            'total_orders_evaluated' => $orders->count(),
            'total_contract_value_minor_cents' => $totalContractValue,
            'total_ledger_credits_minor_cents' => $totalLedgerCredits,
            'discrepancy_amount_minor_cents' => $discrepancy,
            'is_fully_reconciled' => $isReconciled,
            'audit_verdict' => $isReconciled ? 'RECONCILIATION_MATCH_PERFECT' : 'FINANCIAL_DISCREPANCY_DETECTED',
        ];
    }

    /**
     * Rekonsiliasi neraca biomassa fisik seluruh lot panen terhadap lot ekspor olahan.
     *
     * @return array{
     *     total_harvest_biomass_kg: float,
     *     total_export_lots_net_weight_kg: float,
     *     aggregate_conversion_yield_percentage: float,
     *     is_within_biological_limits: bool
     * }
     */
    public function reconcilePhysicalBiomassBalance(): array
    {
        $totalHarvest = (float) AquacultureHarvestOperation::sum('gross_biomass_harvested_kg');
        $totalExport = (float) AquacultureExportLot::sum('net_lot_weight_kg');

        $yieldPct = $totalHarvest > 0.0 ? round(($totalExport / $totalHarvest) * 100.0, 2) : 0.0;
        // Rendemen maksimum agregat wajar untuk udang beku HLSO/HOSO adalah 70%
        $isWithinLimits = ($yieldPct > 0.0 && $yieldPct <= 70.0);

        return [
            'total_harvest_biomass_kg' => round($totalHarvest, 2),
            'total_export_lots_net_weight_kg' => round($totalExport, 2),
            'aggregate_conversion_yield_percentage' => $yieldPct,
            'is_within_biological_limits' => $isWithinLimits,
        ];
    }
}

