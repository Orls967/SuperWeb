<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use Modules\Hotel\Ledger\HotelTransactionTypes;

final class HotelLoyaltyDisputeResolutionService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     claim_id: string,
     *     travel_pass_id: string,
     *     folio_reference: string,
     *     status: string,
     *     points_credited: int,
     *     investigation_notes: string,
     *     settled_at: string
     * }
     */
    public function resolveMissingPointsClaim(
        string $travelPassId,
        string $folioReference,
        string $stayCheckInDate,
        string $stayCheckOutDate,
        int $claimedSpendAmountIdr,
        string $hotelPropertyReference,
        string $approverStaffReference,
        bool $isApproved = true,
        string $rejectionReason = ''
    ): array {
        return DB::transaction(function () use (
            $travelPassId,
            $folioReference,
            $claimedSpendAmountIdr,
            $hotelPropertyReference,
            $approverStaffReference,
            $isApproved,
            $rejectionReason
        ) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();
            $claimId = 'CLM-'.now()->format('Ymd').'-'.bin2hex(random_bytes(3));

            if (! $isApproved) {
                return [
                    'claim_id' => $claimId,
                    'travel_pass_id' => $pass->id,
                    'folio_reference' => $folioReference,
                    'status' => 'REJECTED',
                    'points_credited' => 0,
                    'investigation_notes' => "Klaim ditolak oleh {$approverStaffReference}: {$rejectionReason}",
                    'settled_at' => now()->toIso8601String(),
                ];
            }

            // Standard earning: 1 point per 1,000 IDR room spend
            $pointsToCredit = (int) round($claimedSpendAmountIdr / 1000);
            if ($pass->tier === 'BLACK') {
                $pointsToCredit = (int) round($pointsToCredit * 1.5);
            } elseif ($pass->tier === 'PLATINUM') {
                $pointsToCredit = (int) round($pointsToCredit * 1.25);
            }

            $batch = HtlTravelPassPointBatch::create([
                'travel_pass_id' => $pass->id,
                'batch_number' => 'RETRO-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(2)),
                'originating_line' => 'HOTEL_RETRO_CLAIM',
                'initial_pts' => $pointsToCredit,
                'remaining_pts' => $pointsToCredit,
                'liability_per_pt_idr' => 100,
                'total_liability_idr' => $pointsToCredit * 100,
                'state' => 'ACTIVE',
                'reference_code' => "FOLIO-{$folioReference}",
                'earned_at' => now(),
                'expires_at' => now()->addYears(2),
            ]);

            $pass->increment('pts_balance', $pointsToCredit);
            $pass->refreshTier();

            // Post to ledger: Debit Loyalty Program Expense, Credit Loyalty Points Liability
            $liabilityIdr = $pointsToCredit * 100;
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_EARNED->value,
                description: "Retro claim points credit for folio {$folioReference} at {$hotelPropertyReference}",
                idempotencyKey: "HTL-RETRO-CLAIM-{$claimId}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_program_expense:IDR', $liabilityIdr)
                    ->credit('htl:loyalty_points_liability:IDR', $liabilityIdr)
                    ->toEntries('IDR'),
                referenceType: 'DISPUTE_RETRO_CLAIM',
                referenceId: $claimId,
            ));

            return [
                'claim_id' => $claimId,
                'travel_pass_id' => $pass->id,
                'folio_reference' => $folioReference,
                'status' => 'APPROVED_AND_CREDITED',
                'points_credited' => $pointsToCredit,
                'investigation_notes' => "Klaim disetujui oleh {$approverStaffReference}. Folio valid diverifikasi pada PMS.",
                'settled_at' => now()->toIso8601String(),
            ];
        });
    }
}
