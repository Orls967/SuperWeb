<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use RuntimeException;

/**
 * FEFO (First-Expired, First-Out) Pharmacy Dispensing Engine.
 * Menjamin kepatuhan standar Good Pharmacy Practice (GPP):
 * 1. Alokasi batch obat selalu memprioritaskan tanggal kedaluwarsa paling awal (FEFO).
 * 2. Karantina otomatis untuk lot dengan sisa masa simpan < 90 hari.
 * 3. Pemotongan multi-lot atomik menggunakan database row locking (lockForUpdate).
 * 4. Anti-overselling dan pelarangan saldo persediaan negatif.
 */
class FefoPharmacyDispensingEngine
{
    private const MIN_OUTPATIENT_SHELF_LIFE_DAYS = 90;

    /**
     * Memeriksa ketersediaan total stok FEFO yang memenuhi syarat di cabang apotek tertentu.
     */
    public function getAvailableStock(int $branchId, string $drugCode, int $minShelfLifeDays = self::MIN_OUTPATIENT_SHELF_LIFE_DAYS): int
    {
        $cutoffDate = Carbon::today()->addDays($minShelfLifeDays)->toDateString();

        return (int) PharmacyBranchLot::where('branch_id', $branchId)
            ->where('drug_code', $drugCode)
            ->where('is_quarantined', false)
            ->where('expiry_date', '>=', $cutoffDate)
            ->where('quantity', '>', 0)
            ->sum('quantity');
    }

    /**
     * Mengalokasikan dan memotong stok obat berdasarkan FEFO secara transaksi database aman.
     *
     * @return array{
     *     total_allocated: int,
     *     lots_allocated: array<int, array{
     *         lot_id: int,
     *         lot_number: string,
     *         quantity_picked: int,
     *         remaining_lot_qty: int,
     *         expiry_date: string
     *     }>
     * }
     */
    public function dispenseFefoLots(
        int $branchId,
        string $drugCode,
        int $requestedQuantity,
        int $minShelfLifeDays = self::MIN_OUTPATIENT_SHELF_LIFE_DAYS
    ): array {
        if ($requestedQuantity <= 0) {
            throw new InvalidArgumentException("Jumlah obat yang diminta harus lebih besar dari 0, diberikan: {$requestedQuantity}");
        }

        $cutoffDate = Carbon::today()->addDays($minShelfLifeDays)->toDateString();

        return DB::transaction(function () use ($branchId, $drugCode, $requestedQuantity, $cutoffDate) {
            // Ambil semua lot aktif yang memenuhi syarat, urutkan tanggal kedaluwarsa paling dini (FEFO)
            $availableLots = PharmacyBranchLot::where('branch_id', $branchId)
                ->where('drug_code', $drugCode)
                ->where('is_quarantined', false)
                ->where('expiry_date', '>=', $cutoffDate)
                ->where('quantity', '>', 0)
                ->orderBy('expiry_date', 'asc')
                ->lockForUpdate()
                ->get();

            $totalAvailable = $availableLots->sum('quantity');
            if ($totalAvailable < $requestedQuantity) {
                throw new RuntimeException(
                    "Stok obat '{$drugCode}' di cabang apotek ID {$branchId} tidak mencukupi. Tersedia: {$totalAvailable}, Diminta: {$requestedQuantity}."
                );
            }

            $remainingToPick = $requestedQuantity;
            $allocatedRecords = [];

            foreach ($availableLots as $lot) {
                if ($remainingToPick <= 0) {
                    break;
                }

                $qtyFromThisLot = min($lot->quantity, $remainingToPick);
                $lot->quantity -= $qtyFromThisLot;
                $lot->save();

                $remainingToPick -= $qtyFromThisLot;

                $allocatedRecords[] = [
                    'lot_id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'quantity_picked' => $qtyFromThisLot,
                    'remaining_lot_qty' => $lot->quantity,
                    'expiry_date' => $lot->expiry_date instanceof Carbon ? $lot->expiry_date->toDateString() : (string) $lot->expiry_date,
                ];
            }

            if ($remainingToPick > 0) {
                throw new RuntimeException("Gagal memenuhi total kuantitas FEFO untuk obat '{$drugCode}'.");
            }

            return [
                'total_allocated' => $requestedQuantity,
                'lots_allocated' => $allocatedRecords,
            ];
        });
    }

    /**
     * Memulihkan / mengembalikan stok obat yang telah dialokasikan (misalnya jika pesanan dibatalkan).
     */
    public function reverseDispensing(array $allocationDetails): void
    {
        DB::transaction(function () use ($allocationDetails): void {
            foreach ($allocationDetails['lots_allocated'] ?? [] as $item) {
                $lot = PharmacyBranchLot::lockForUpdate()->find($item['lot_id']);
                if ($lot) {
                    $lot->quantity += (int) $item['quantity_picked'];
                    $lot->save();
                }
            }
        });
    }
}
