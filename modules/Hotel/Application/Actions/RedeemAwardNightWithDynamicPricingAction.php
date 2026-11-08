<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Application\DTOs\AwardNightBookingDTO;
use Modules\Hotel\Domain\Algorithms\AwardNightPricer;
use Modules\Hotel\Domain\Algorithms\PointsLiabilityFifo;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class RedeemAwardNightWithDynamicPricingAction
{
    public function __construct(
        protected ?AwardNightPricer $pricer = null,
        protected ?PointsLiabilityFifo $fifo = null,
        protected ?LedgerService $ledgerService = null
    ) {
        $this->pricer = $pricer ?? new AwardNightPricer;
        $this->fifo = $fifo ?? new PointsLiabilityFifo;
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    public function execute(
        string $travelPassId,
        string $propertyReference,
        string $roomTypeReference,
        string $stayDate,
        float $currentOccupancyRate = 0.70,
        int $marginalCostIdr = 300000,
        int $basePoints = 15000
    ): AwardNightBookingDTO {
        return DB::transaction(function () use (
            $travelPassId,
            $propertyReference,
            $roomTypeReference,
            $stayDate,
            $currentOccupancyRate,
            $marginalCostIdr,
            $basePoints
        ) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();

            // 1. Calculate dynamic points required
            $cert = $this->pricer->calculate(
                basePointsRequired: $basePoints,
                occupancyRate: $currentOccupancyRate,
                seasonalityMultiplier: 1.0,
                marginalCostIdr: $marginalCostIdr,
                tier: $pass->tier,
                pointValuationIdr: 100
            );

            if ($pass->pts_balance < $cert->finalPointsRequired) {
                throw new RuntimeException("Saldo poin tidak mencukupi untuk memesan kamar: Dibutuhkan {$cert->finalPointsRequired} PTS, saldo Anda {$pass->pts_balance} PTS");
            }

            // 2. Fetch active batches and allocate FIFO
            $batches = HtlTravelPassPointBatch::where('travel_pass_id', $pass->id)
                ->whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED'])
                ->lockForUpdate()
                ->get();

            $batchList = [];
            foreach ($batches as $b) {
                $batchList[] = [
                    'batch_id' => $b->id,
                    'remaining_pts' => (int) $b->remaining_pts,
                    'liability_per_pt_idr' => (int) $b->liability_per_pt_idr,
                    'expires_at_timestamp' => $b->expires_at->getTimestamp(),
                ];
            }

            $fifoCert = $this->fifo->allocate(
                batches: $batchList,
                ptsToRedeem: $cert->finalPointsRequired,
                currentTimestamp: now()->getTimestamp()
            );

            // Deduct batches
            foreach ($fifoCert->redemptionAllocations as $alloc) {
                $bModel = $batches->firstWhere('id', $alloc['batch_id']);
                if ($bModel) {
                    $newRem = $bModel->remaining_pts - $alloc['deducted_pts'];
                    $bModel->update([
                        'remaining_pts' => $newRem,
                        'state' => $newRem === 0 ? 'FULLY_REDEEMED' : 'PARTIALLY_REDEEMED',
                    ]);
                }
            }

            // Deduct pass
            $pass->decrement('pts_balance', $cert->finalPointsRequired);

            $bookingId = (string) Str::uuid();
            $confirmationCode = 'AWD-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));

            // 3. Post to ledger: Debit Points Liability, Credit Redemption Settlement / Room Revenue
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_REDEEMED->value,
                description: "Award night booking {$confirmationCode} at {$propertyReference} for {$stayDate}",
                idempotencyKey: "HTL-AWD-{$bookingId}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_points_liability:IDR', $cert->reimbursementValueIdr)
                    ->credit('htl:room_revenue:IDR', $cert->reimbursementValueIdr)
                    ->toEntries('IDR'),
                referenceType: 'AWARD_BOOKING',
                referenceId: $bookingId,
            ));

            return new AwardNightBookingDTO(
                bookingId: $bookingId,
                travelPassId: $pass->id,
                propertyReference: $propertyReference,
                roomTypeReference: $roomTypeReference,
                stayDate: $stayDate,
                pointsRedeemed: $cert->finalPointsRequired,
                propertyReimbursementIdr: $cert->reimbursementValueIdr,
                isFloorApplied: $cert->isFloorApplied,
                confirmationCode: $confirmationCode
            );
        });
    }
}
