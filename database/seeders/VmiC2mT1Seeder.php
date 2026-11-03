<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VmiC2mT1Seeder extends Seeder
{
    /**
     * D82 Simulasi T1: 1.000 SKU, 50 pemasok VMI, 200 desain C2M.
     */
    public function run(): void
    {
        $now = '2026-11-04 00:00:00';

        // 1. 1.000 SKU tersebar di 50 pemasok VMI
        $agreements = [];
        $replenishments = [];
        $ledgerTransactions = [];

        for ($i = 1; $i <= 1000; $i++) {
            $supplierId = ($i % 50) + 1;
            $sku = sprintf('SKU-VMI-%04d', $i);
            $agrCode = sprintf('AGR-%04d', $i);
            $vmiCode = sprintf('VMI-T1-%04d', $i);
            $unitCost = 10_000 + (($i % 10) * 1_000);
            $plafond = 50_000_000;
            $qty = 100;
            $totalEst = $qty * $unitCost;
            $ledgerKey = "VMI-COMM-{$vmiCode}";

            $agreements[] = [
                'agreement_code' => $agrCode,
                'supplier_id' => $supplierId,
                'sku' => $sku,
                'reorder_point' => 50,
                'reorder_qty' => $qty,
                'unit_cost_idr' => $unitCost,
                'contract_plafond_idr' => $plafond,
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $replenishments[] = [
                'vmi_code' => $vmiCode,
                'supplier_id' => $supplierId,
                'sku' => $sku,
                'current_shelf_stock' => 30,
                'reorder_point' => 50,
                'suggested_reorder_qty' => $qty,
                'total_estimated_idr' => $totalEst,
                'contract_plafond_idr' => $plafond,
                'status' => 'AUTO_ORDERED',
                'idempotency_key' => "VMI:{$supplierId}:{$sku}:2026-11-04",
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $ledgerTransactions[] = [
                'uuid' => hash('sha256', "TX:{$ledgerKey}"),
                'type' => 'VMI_PURCHASE_COMMITMENT',
                'reference_type' => 'VMI_REPLENISHMENT',
                'reference_id' => (string) $i,
                'idempotency_key' => $ledgerKey,
                'description' => "VMI auto PO commitment for {$sku} qty {$qty}",
                'meta' => json_encode(['sku' => $sku, 'total' => $totalEst]),
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($agreements, 250) as $chunk) {
            DB::table('prc_vmi_agreements')->insert($chunk);
        }

        foreach (array_chunk($replenishments, 250) as $chunk) {
            DB::table('mfg_vmi_replenishments')->insert($chunk);
        }

        foreach (array_chunk($ledgerTransactions, 250) as $chunk) {
            DB::table('bank_ledger_transactions')->insert($chunk);
        }

        // 2. 200 desain C2M kustom beserta BOM nodes
        $customOrders = [];
        for ($j = 1; $j <= 200; $j++) {
            $c2mCode = sprintf('C2M-T1-%04d', $j);
            $rawCost = 1_000_000 + (($j % 5) * 200_000);
            $finalPrice = (int) round($rawCost * 1.25);

            $customOrders[] = [
                'id' => 1000 + $j,
                'c2m_code' => $c2mCode,
                'user_id' => ($j % 20) + 1,
                'base_model' => 'CUSTOM_DESK_MODULAR',
                'parameters' => json_encode(['width' => 100, 'depth' => 60, 'material' => 'kayu', 'drawers' => 4]),
                'complexity_factor' => 1.25,
                'rolled_up_bom_cost_idr' => $rawCost,
                'final_price_idr' => $finalPrice,
                'status' => 'DESIGN_VALIDATED',
                'estimated_lead_days' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($customOrders, 100) as $chunk) {
            DB::table('mfg_c2m_custom_orders')->insert($chunk);
        }
    }
}
