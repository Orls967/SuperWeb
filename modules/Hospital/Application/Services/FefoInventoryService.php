<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\PharmacyBranchLot;
use RuntimeException;

class FefoInventoryService
{
    /**
     * Pick medication stock following First-Expired First-Out (FEFO) order
     *
     * @return array<int, array{lot_number: string, picked_qty: int, expiry_date: string}>
     */
    public function pickStock(int $branchId, string $drugCode, int $requestedQty): array
    {
        if ($requestedQty <= 0) {
            throw new RuntimeException('Requested quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($branchId, $drugCode, $requestedQty) {
            $lots = PharmacyBranchLot::where('branch_id', $branchId)
                ->where('drug_code', $drugCode)
                ->where('is_quarantined', false)
                ->where('quantity', '>', 0)
                ->whereDate('expiry_date', '>', now()->toDateString())
                ->orderBy('expiry_date', 'asc')
                ->lockForUpdate()
                ->get();

            $totalAvailable = $lots->sum('quantity');
            if ($totalAvailable < $requestedQty) {
                throw new RuntimeException("Insufficient FEFO stock for {$drugCode}. Available: {$totalAvailable}, Requested: {$requestedQty}");
            }

            $remaining = $requestedQty;
            $picks = [];

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $deduct = min($remaining, $lot->quantity);
                $lot->quantity -= $deduct;
                $lot->save();

                $picks[] = [
                    'lot_number' => $lot->lot_number,
                    'picked_qty' => $deduct,
                    'expiry_date' => $lot->expiry_date->toDateString(),
                ];

                $remaining -= $deduct;
            }

            return $picks;
        });
    }

    /**
     * Quarantine a contaminated or recalled lot across branch
     */
    public function quarantineLot(int $branchId, string $lotNumber): void
    {
        PharmacyBranchLot::where('branch_id', $branchId)
            ->where('lot_number', $lotNumber)
            ->update(['is_quarantined' => true]);
    }

    /**
     * Get total unexpired, non-quarantined available stock
     */
    public function getAvailableStock(int $branchId, string $drugCode): int
    {
        return (int) PharmacyBranchLot::where('branch_id', $branchId)
            ->where('drug_code', $drugCode)
            ->where('is_quarantined', false)
            ->whereDate('expiry_date', '>', now()->toDateString())
            ->sum('quantity');
    }
}
