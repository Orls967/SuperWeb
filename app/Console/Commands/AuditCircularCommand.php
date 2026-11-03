<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Manufacturing\Domain\Models\MaterialLot;
use Modules\Manufacturing\Domain\Models\RecycledMaterialReceipt;

class AuditCircularCommand extends Command
{
    protected $signature = 'logi:circular-audit';

    protected $description = 'Audit dua-sumber: verifikasi shipment reverse received == lot bahan baku sekunder & saldo kliring sirkular';

    public function handle(): int
    {
        $this->info('Memulai audit dua-sumber Reverse Logistics & Ekosistem Sirkular...');

        $discrepancies = [];

        // ═══════════════════════════════════════════════════════════
        // SUMBER 1: Konsistensi Operasional (Shipment -> Receipt -> Lot)
        // ═══════════════════════════════════════════════════════════
        $receivedReverseShipments = Shipment::where('is_reverse', true)
            ->where('status', ShipmentStatus::Received->value)
            ->get();

        $shipmentsAudited = $receivedReverseShipments->count();
        $lotsAudited = 0;

        foreach ($receivedReverseShipments as $shipment) {
            // 1. Rantai Kustodi Hash
            if (empty($shipment->reverse_chain_hash) || strlen($shipment->reverse_chain_hash) !== 64) {
                $discrepancies[] = "Shipment #{$shipment->tracking_number} memiliki rantai kustodi hash tidak valid atau kosong.";
            }

            // 2. Keberadaan Plant Receipt ID
            if (! $shipment->plant_receipt_id) {
                $discrepancies[] = "Shipment received #{$shipment->tracking_number} belum memiliki tautan plant_receipt_id.";
                continue;
            }

            /** @var RecycledMaterialReceipt|null $receipt */
            $receipt = RecycledMaterialReceipt::find($shipment->plant_receipt_id);
            if (! $receipt) {
                $discrepancies[] = "Shipment #{$shipment->tracking_number} merujuk ke receipt #{$shipment->plant_receipt_id} yang tidak ditemukan.";
                continue;
            }

            // 3. Verifikasi Timbangan Fisik vs Receipt
            $expectedWeight = $shipment->verified_weight_grams ?? $shipment->weight_grams;
            if ($expectedWeight !== $receipt->quantity_grams) {
                $discrepancies[] = "Selisih kuantitas timbang shipment #{$shipment->tracking_number} ({$expectedWeight}g) vs receipt #{$receipt->id} ({$receipt->quantity_grams}g).";
            }

            // 4. Verifikasi Keberadaan Lot Bahan Baku di Manufaktur
            if (empty($receipt->material_lot_id)) {
                $discrepancies[] = "Receipt #{$receipt->id} tidak memiliki tautan lot manufaktur (material_lot_id kosong).";
            } else {
                $lot = MaterialLot::find($receipt->material_lot_id);
                if (! $lot) {
                    $discrepancies[] = "Lot manufaktur #{$receipt->material_lot_id} untuk receipt #{$receipt->id} tidak ditemukan di database.";
                } else {
                    $lotsAudited++;
                    $expectedLotQtyKg = round($receipt->quantity_grams / 1000, 6);
                    if (abs((float) $lot->qty - $expectedLotQtyKg) > 0.001) {
                        $discrepancies[] = "Kuantitas lot manufaktur #{$lot->id} ({$lot->qty} kg) tidak sinkron dengan kuantitas receipt ({$expectedLotQtyKg} kg).";
                    }
                }
            }
        }

        // ═══════════════════════════════════════════════════════════
        // SUMBER 2: Konsistensi Keuangan & Buku Besar (Ledger Accounts)
        // ═══════════════════════════════════════════════════════════
        if (Schema::hasTable('bk_ledger_accounts')) {
            // A. Saldo Kliring Manufaktur vs Total Nilai Receipt Diterima
            $totalAcceptedValueIdr = (int) RecycledMaterialReceipt::sum('total_value_idr');

            $clearingRow = DB::selectOne(
                'SELECT cached_balance FROM bk_ledger_accounts WHERE code = ? LIMIT 1',
                ['mfg:recycled_material_clearing:IDR']
            );

            if ($clearingRow !== null) {
                $actualClearingBalance = abs((int) $clearingRow->cached_balance);
                if ($actualClearingBalance !== $totalAcceptedValueIdr) {
                    $discrepancies[] = "Ketidaksesuaian saldo kliring mfg:recycled_material_clearing:IDR: diharapkan Rp {$totalAcceptedValueIdr}, aktual Rp {$actualClearingBalance}.";
                }
            }

            // B. Saldo Beban Logistik Balik vs Akumulasi Carrier Cost
            $totalCompletedCarrierCost = (int) Shipment::where('is_reverse', true)
                ->whereIn('status', [ShipmentStatus::DeliveredToPlant->value, ShipmentStatus::Received->value])
                ->sum('carrier_cost_idr');

            $carrierCostRow = DB::selectOne(
                'SELECT cached_balance FROM bk_ledger_accounts WHERE code = ? LIMIT 1',
                ['lgx:carrier_cost:IDR']
            );

            if ($carrierCostRow !== null && $totalCompletedCarrierCost > 0) {
                $actualCarrierCostBalance = abs((int) $carrierCostRow->cached_balance);
                if ($actualCarrierCostBalance !== $totalCompletedCarrierCost) {
                    $discrepancies[] = "Ketidaksesuaian saldo beban logistik lgx:carrier_cost:IDR: diharapkan Rp {$totalCompletedCarrierCost}, aktual Rp {$actualCarrierCostBalance}.";
                }
            }
        }

        $passed = empty($discrepancies);

        $this->table(
            ['Metrik Audit', 'Nilai'],
            [
                ['Shipments Reverse Received Diaudit', $shipmentsAudited],
                ['Lots Bahan Baku Sekunder Terverifikasi', $lotsAudited],
                ['Selisih Ditemukan (Discrepancies)', count($discrepancies)],
                ['Hasil Audit Dua-Sumber', $passed ? 'PASS (0 SELISIH)' : 'FAIL'],
            ]
        );

        if (! $passed) {
            $this->error('Ditemukan ketidaksesuaian integritas sirkular dua-sumber:');
            foreach ($discrepancies as $error) {
                $this->error("  ✗ {$error}");
            }

            return self::FAILURE;
        }

        $this->info("✓ Seluruh {$shipmentsAudited} shipment reverse received sinkron 1:1 dengan lot bahan baku sekunder dan saldo kliring buku besar (0 selisih).");

        return self::SUCCESS;
    }
}
