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
use RuntimeException;

final class HotelPointsGiftAndPoolTransferService
{
    public const MAX_ANNUAL_GIFT_POINTS = 100000;

    public const GIFT_ADMIN_FEE_PER_POINT_IDR = 5; // Rp 5 per point transfer fee

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     gift_transfer_id: string,
     *     sender_pass_id: string,
     *     recipient_pass_id: string,
     *     points_gifted: int,
     *     admin_fee_idr: int,
     *     custom_message: string,
     *     transferred_at: string
     * }
     */
    public function giftPointsToAnotherMember(
        string $senderPassId,
        string $recipientPassId,
        int $pointsToGift,
        string $customMessage = 'Selamat menikmati liburan bersama SuperWeb Travel Pass!'
    ): array {
        if ($senderPassId === $recipientPassId) {
            throw new RuntimeException('Pengirim dan penerima gift poin tidak boleh sama.');
        }

        if ($pointsToGift < 1000) {
            throw new RuntimeException("Minimal gift poin adalah 1.000 PTS (diminta: {$pointsToGift} PTS)");
        }

        if ($pointsToGift > self::MAX_ANNUAL_GIFT_POINTS) {
            throw new RuntimeException('Melebihi batas maksimal gift tahunan yaitu '.self::MAX_ANNUAL_GIFT_POINTS.' PTS');
        }

        return DB::transaction(function () use (
            $senderPassId,
            $recipientPassId,
            $pointsToGift,
            $customMessage
        ) {
            $sender = HtlTravelPass::where('id', $senderPassId)->lockForUpdate()->firstOrFail();
            $recipient = HtlTravelPass::where('id', $recipientPassId)->lockForUpdate()->firstOrFail();

            if ($sender->pts_balance < $pointsToGift) {
                throw new RuntimeException("Saldo poin pengirim tidak mencukupi: Saldo {$sender->pts_balance} PTS, diminta {$pointsToGift} PTS");
            }

            // Deduct sender
            $sender->decrement('pts_balance', $pointsToGift);

            // Re-batch points for recipient
            $batches = HtlTravelPassPointBatch::where('travel_pass_id', $sender->id)
                ->whereIn('state', ['ACTIVE', 'PARTIALLY_REDEEMED'])
                ->lockForUpdate()
                ->get();

            $remainingNeeded = $pointsToGift;
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

                HtlTravelPassPointBatch::create([
                    'travel_pass_id' => $recipient->id,
                    'batch_number' => 'GIFT-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(2)),
                    'originating_line' => 'HOTEL_GIFT_TRANSFER',
                    'initial_pts' => $deduct,
                    'remaining_pts' => $deduct,
                    'liability_per_pt_idr' => 100,
                    'total_liability_idr' => $deduct * 100,
                    'state' => 'ACTIVE',
                    'reference_code' => "GIFT-FROM-{$sender->pass_number}",
                    'earned_at' => now(),
                    'expires_at' => now()->addYear(),
                ]);

                $remainingNeeded -= $deduct;
            }

            // Increment recipient
            $recipient->increment('pts_balance', $pointsToGift);
            $recipient->refreshTier();

            $adminFeeIdr = $pointsToGift * self::GIFT_ADMIN_FEE_PER_POINT_IDR;
            $giftId = 'GIFT-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4));

            // Ledger fee posting
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_EARNED->value,
                description: "Gift points fee from {$sender->pass_number} to {$recipient->pass_number}: {$pointsToGift} PTS",
                idempotencyKey: "HTL-GIFT-FEE-{$giftId}",
                entries: Posting::lines()
                    ->debit('clearing:external:IDR', $adminFeeIdr)
                    ->credit('htl:transfer_fee_revenue:IDR', $adminFeeIdr)
                    ->toEntries('IDR'),
                referenceType: 'GIFT_TRANSFER_FEE',
                referenceId: $giftId,
            ));

            return [
                'gift_transfer_id' => $giftId,
                'sender_pass_id' => $sender->id,
                'recipient_pass_id' => $recipient->id,
                'points_gifted' => $pointsToGift,
                'admin_fee_idr' => $adminFeeIdr,
                'custom_message' => $customMessage,
                'transferred_at' => now()->toIso8601String(),
            ];
        });
    }
}
