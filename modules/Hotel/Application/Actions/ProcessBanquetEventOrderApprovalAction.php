<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use InvalidArgumentException;
use Modules\Hotel\Domain\Models\MiceContract;

/**
 * Tindakan Evaluasi & Persetujuan Dokumen Banquet Event Order (BEO)
 * dengan Proteksi Batas Marjin Kotor Minimal 40%.
 */
final class ProcessBanquetEventOrderApprovalAction
{
    /**
     * Ambang batas marjin kotor minimal (40.0%).
     */
    public const MINIMUM_GROSS_MARGIN_PERCENT = 40.0;

    /**
     * @param  array<int, array{item_name: string, quantity: int, unit_cost_idr: int}>  $kitchenBomItems
     * @return array{contract_id: string, gross_margin_pct: float, is_approved: bool, reason: string}
     */
    public function execute(
        string $miceContractId,
        int $contractValueIdr,
        array $kitchenBomItems,
        int $avEquipmentCostIdr,
        int $decorFloristCostIdr
    ): array {
        if ($contractValueIdr <= 0) {
            throw new InvalidArgumentException('Nilai kontrak banquet harus lebih besar dari 0.');
        }

        // Hitung total biaya BOM dapur
        $totalKitchenBomCost = 0;
        foreach ($kitchenBomItems as $item) {
            $totalKitchenBomCost += ($item['quantity'] * $item['unit_cost_idr']);
        }

        $totalProductionCost = $totalKitchenBomCost + $avEquipmentCostIdr + $decorFloristCostIdr;

        // Hitung persentase marjin kotor
        $grossProfit = $contractValueIdr - $totalProductionCost;
        $grossMarginPct = round(($grossProfit / (float) $contractValueIdr) * 100.0, 2);

        $contract = MiceContract::find($miceContractId);

        if ($grossMarginPct < self::MINIMUM_GROSS_MARGIN_PERCENT) {
            if ($contract) {
                $contract->update([
                    'status' => 'CANCELLED',
                    'actual_banquet_cost_idr' => $totalProductionCost,
                ]);
            }

            return [
                'contract_id' => $miceContractId,
                'gross_margin_pct' => $grossMarginPct,
                'is_approved' => false,
                'reason' => "Pemesanan ditolak: Marjin kotor ({$grossMarginPct}%) berada di bawah batas minimum 40.0%.",
            ];
        }

        if ($contract) {
            $contract->update([
                'status' => 'CONFIRMED',
                'actual_banquet_cost_idr' => $totalProductionCost,
            ]);
        }

        return [
            'contract_id' => $miceContractId,
            'gross_margin_pct' => $grossMarginPct,
            'is_approved' => true,
            'reason' => 'Pemesanan banquet disetujui memenuhi standar marjin kotor.',
        ];
    }
}
