<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Application\DTOs\BreakageAuditResultDTO;
use Modules\Hotel\Domain\Algorithms\PointsLiabilityFifo;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use Modules\Hotel\Ledger\HotelTransactionTypes;

final class RecognizeLoyaltyBreakageRevenueAction
{
    public function __construct(
        protected ?LedgerService $ledgerService = null,
        protected ?PointsLiabilityFifo $fifoEngine = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
        $this->fifoEngine = $fifoEngine ?? new PointsLiabilityFifo;
    }

    public function execute(?string $travelPassId = null): BreakageAuditResultDTO
    {
        return DB::transaction(function () use ($travelPassId) {
            $query = HtlTravelPassPointBatch::query();
            if ($travelPassId) {
                $query->where('travel_pass_id', $travelPassId);
            }

            $batches = $query->whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED', 'EXPIRED'])
                ->lockForUpdate()
                ->get();

            $batchArray = [];
            foreach ($batches as $b) {
                $batchArray[] = [
                    'batch_id' => $b->id,
                    'remaining_pts' => (int) $b->remaining_pts,
                    'liability_per_pt_idr' => (int) $b->liability_per_pt_idr,
                    'expires_at_timestamp' => $b->expires_at->getTimestamp(),
                ];
            }

            $cert = $this->fifoEngine->allocate(
                batches: $batchArray,
                ptsToRedeem: 0,
                currentTimestamp: now()->getTimestamp()
            );

            if ($cert->totalExpiredPts > 0) {
                // Update batches in db
                foreach ($cert->expiredBatches as $exp) {
                    $bModel = $batches->firstWhere('id', $exp['batch_id']);
                    if ($bModel) {
                        $bModel->update([
                            'state' => 'BREAKAGE_WRITTEN_OFF',
                            'remaining_pts' => 0,
                            'breakage_recognized_at' => now(),
                        ]);

                        // Decrement travel pass points balance
                        $pass = HtlTravelPass::find($bModel->travel_pass_id);
                        if ($pass) {
                            $pass->decrement('pts_balance', $exp['expired_pts']);
                        }
                    }
                }

                // Post breakage recognition to ledger:
                // Debit Points Liability, Credit Breakage Revenue
                $this->ledgerService->post(new PostingDTO(
                    type: HotelTransactionTypes::HTL_LOYALTY_BREAKAGE->value,
                    description: "Recognition of {$cert->totalExpiredPts} expired PTS as breakage revenue",
                    idempotencyKey: 'HTL-BREAKAGE-'.now()->format('YmdHis').'-'.$cert->signatureHash,
                    entries: Posting::lines()
                        ->debit('htl:loyalty_points_liability:IDR', $cert->totalBreakageRevenueIdr)
                        ->credit('htl:loyalty_breakage_revenue:IDR', $cert->totalBreakageRevenueIdr)
                        ->toEntries('IDR'),
                    referenceType: 'BREAKAGE_AUDIT',
                    referenceId: $cert->signatureHash,
                ));
            }

            return new BreakageAuditResultDTO(
                totalBatchesScanned: count($batches),
                totalExpiredBatches: count($cert->expiredBatches),
                totalExpiredPts: $cert->totalExpiredPts,
                breakageRevenueIdr: $cert->totalBreakageRevenueIdr,
                expiredBatches: $cert->expiredBatches
            );
        });
    }
}
