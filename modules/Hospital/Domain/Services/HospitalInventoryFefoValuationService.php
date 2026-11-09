<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;

/**
 * Layanan Domain Penilaian Persediaan & Alokasi FEFO Farmasi Rumah Sakit (Hospital FEFO Inventory Valuation).
 * Mengelola perputaran stok obat berdasarkan tanggal kadaluarsa (First-Expired-First-Out - FEFO):
 *   1. Alokasi batch obat otomatis mengutamakan lot yang paling cepat mendekati tanggal kadaluarsa.
 *   2. Penilaian saldo nilai persediaan farmasi pada harga perolehan (Cost Basis).
 *   3. Pembentukan provisi kerugian persediaan kadaluarsa / rusak (Expired Stock Provision).
 *   4. Deteksi dini stok berisiko kadaluarsa dalam 30, 60, dan 90 hari (Near-Expiry Early Warning).
 */
class HospitalInventoryFefoValuationService
{
    /**
     * Mengalokasikan stok obat untuk dispensing resep menggunakan algoritma prioritas FEFO.
     *
     * @param  string  $drugCode  Kode obat farmasi
     * @param  int  $requiredQuantity  Jumlah kuantitas yang dibutuhkan dalam resep
     * @return array{
     *     drug_code: string,
     *     allocated_quantity: int,
     *     unfulfilled_quantity: int,
     *     allocated_batches: list<array{lot_number: string, quantity_drawn: int, expiry_date: string, unit_cost_idr: int}>,
     *     total_cost_idr: int
     * }
     */
    public function allocateStockFefo(string $drugCode, int $requiredQuantity): array
    {
        if ($requiredQuantity <= 0) {
            throw new DomainException('Jumlah kuantitas kebutuhan obat harus lebih dari nol.');
        }

        // Ambil lot obat yang aktif (tidak dikarantina dan belum kadaluarsa), diurutkan dari expiry_date terdekat
        $availableLots = PharmacyBranchLot::where('drug_code', $drugCode)
            ->where('is_quarantined', false)
            ->where('expiry_date', '>', now())
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->get();

        $remainingNeeded = $requiredQuantity;
        $allocatedBatches = [];
        $totalCostIdr = 0;

        foreach ($availableLots as $lot) {
            if ($remainingNeeded <= 0) {
                break;
            }

            $draw = min($remainingNeeded, (int) $lot->quantity);
            $unitCost = (int) ($lot->unit_cost_idr ?? 1000);
            $subtotalCost = $draw * $unitCost;

            $allocatedBatches[] = [
                'lot_number' => $lot->lot_number,
                'quantity_drawn' => $draw,
                'expiry_date' => $lot->expiry_date ? $lot->expiry_date->toDateString() : 'N/A',
                'unit_cost_idr' => $unitCost,
            ];

            $totalCostIdr += $subtotalCost;
            $remainingNeeded -= $draw;
        }

        $allocatedQty = $requiredQuantity - $remainingNeeded;

        return [
            'drug_code' => $drugCode,
            'allocated_quantity' => $allocatedQty,
            'unfulfilled_quantity' => $remainingNeeded,
            'allocated_batches' => $allocatedBatches,
            'total_cost_idr' => $totalCostIdr,
        ];
    }

    /**
     * Menghasilkan laporan valuasi nilai persediaan farmasi dan analisis risiko kadaluarsa.
     *
     * @return array{
     *     total_inventory_value_idr: int,
     *     total_active_lots: int,
     *     quarantined_lots_value_idr: int,
     *     near_expiry_30_days_value_idr: int,
     *     near_expiry_60_days_value_idr: int,
     *     near_expiry_90_days_value_idr: int,
     *     estimated_spoilage_allowance_idr: int
     * }
     */
    public function generateInventoryValuationReport(): array
    {
        $allLots = PharmacyBranchLot::where('quantity', '>', 0)->get();

        $totalVal = 0;
        $quarantinedVal = 0;
        $near30Val = 0;
        $near60Val = 0;
        $near90Val = 0;

        $now = now();

        foreach ($allLots as $lot) {
            $cost = ((int) $lot->quantity) * ((int) ($lot->unit_cost_idr ?? 1000));
            $totalVal += $cost;

            if ($lot->is_quarantined) {
                $quarantinedVal += $cost;
                continue;
            }

            if ($lot->expiry_date) {
                $daysToExpiry = (int) $now->diffInDays($lot->expiry_date, false);
                if ($daysToExpiry <= 30) {
                    $near30Val += $cost;
                } elseif ($daysToExpiry <= 60) {
                    $near60Val += $cost;
                } elseif ($daysToExpiry <= 90) {
                    $near90Val += $cost;
                }
            }
        }

        // Provisi kerugian susut obat: 100% dari obat karantina + 50% obat <30 hari + 20% obat 31-60 hari
        $spoilageAllowance = (int) round(
            $quarantinedVal +
            ($near30Val * 0.50) +
            ($near60Val * 0.20) +
            ($near90Val * 0.05)
        );

        return [
            'total_inventory_value_idr' => $totalVal,
            'total_active_lots' => count($allLots),
            'quarantined_lots_value_idr' => $quarantinedVal,
            'near_expiry_30_days_value_idr' => $near30Val,
            'near_expiry_60_days_value_idr' => $near60Val,
            'near_expiry_90_days_value_idr' => $near90Val,
            'estimated_spoilage_allowance_idr' => $spoilageAllowance,
        ];
    }
}
