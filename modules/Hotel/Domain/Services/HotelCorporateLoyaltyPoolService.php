<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use RuntimeException;

final class HotelCorporateLoyaltyPoolService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     pool_allocation_id: string,
     *     corporate_master_pass_id: string,
     *     employee_pass_id: string,
     *     allocated_points: int,
     *     purpose: string,
     *     allocated_at: string
     * }
     */
    public function allocatePointsFromCorporateMasterToEmployee(
        string $corporateMasterPassId,
        string $employeePassId,
        int $pointsToAllocate,
        string $travelPurpose = 'BUSINESS_TRIP_MICE_DELEGATION'
    ): array {
        if ($pointsToAllocate <= 0) {
            throw new RuntimeException("Jumlah alokasi poin korporat harus lebih dari 0: {$pointsToAllocate}");
        }

        return DB::transaction(function () use (
            $corporateMasterPassId,
            $employeePassId,
            $pointsToAllocate,
            $travelPurpose
        ) {
            $master = HtlTravelPass::where('id', $corporateMasterPassId)->lockForUpdate()->firstOrFail();
            $employee = HtlTravelPass::where('id', $employeePassId)->lockForUpdate()->firstOrFail();

            if ($master->pts_balance < $pointsToAllocate) {
                throw new RuntimeException("Saldo pool korporat tidak mencukupi: Saldo {$master->pts_balance} PTS, diminta {$pointsToAllocate} PTS");
            }

            // Deduct master pass
            $master->decrement('pts_balance', $pointsToAllocate);

            // Re-allocate active point batches
            $batches = HtlTravelPassPointBatch::where('travel_pass_id', $master->id)
                ->whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED'])
                ->lockForUpdate()
                ->get();

            $remainingNeeded = $pointsToAllocate;
            foreach ($batches as $batch) {
                if ($remainingNeeded <= 0) {
                    break;
                }

                $available = (int) $batch->remaining_pts;
                $deduct = min($available, $remainingNeeded);

                $batch->decrement('remaining_pts', $deduct);
                if ($batch->remaining_pts === 0) {
                    $batch->update(['state' => 'FULLY_REDEEMED']);
                }

                // Create sub-batch for employee
                HtlTravelPassPointBatch::create([
                    'travel_pass_id' => $employee->id,
                    'batch_number' => 'CORP-ALLOC-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(2)),
                    'originating_line' => 'HOTEL_CORPORATE_POOL',
                    'initial_pts' => $deduct,
                    'remaining_pts' => $deduct,
                    'liability_per_pt_idr' => 100,
                    'total_liability_idr' => $deduct * 100,
                    'state' => 'ACTIVE',
                    'reference_code' => "ALLOC-FROM-{$master->pass_number}",
                    'earned_at' => now(),
                    'expires_at' => now()->addMonths(6),
                ]);

                $remainingNeeded -= $deduct;
            }

            // Increment employee pass
            $employee->increment('pts_balance', $pointsToAllocate);
            $employee->refreshTier();

            $allocationId = 'CORP-ALC-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3));

            return [
                'pool_allocation_id' => $allocationId,
                'corporate_master_pass_id' => $master->id,
                'employee_pass_id' => $employee->id,
                'allocated_points' => $pointsToAllocate,
                'purpose' => $travelPurpose,
                'allocated_at' => now()->toIso8601String(),
            ];
        });
    }
}
