<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelMultiPropertyRewardPointSettlementService
{
    public const PROGRAM_ADMIN_MARKUP_BPS = 500; // 5%

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     clearing_id: string,
     *     issuing_property_ref: string,
     *     redeeming_property_ref: string,
     *     points_amount: int,
     *     base_reimbursement_idr: int,
     *     admin_fee_idr: int,
     *     net_reimbursement_idr: int,
     *     status: string,
     *     cleared_at: string
     * }
     */
    public function settleCrossPropertyRedemption(
        string $issuingPropertyRef,
        string $redeemingPropertyRef,
        int $pointsAmount,
        int $baseReimbursementIdr
    ): array {
        if ($pointsAmount <= 0 || $baseReimbursementIdr <= 0) {
            throw new RuntimeException('Jumlah poin dan nilai penggantian biaya kamar harus bernilai positif.');
        }

        return DB::transaction(function () use (
            $issuingPropertyRef,
            $redeemingPropertyRef,
            $pointsAmount,
            $baseReimbursementIdr
        ) {
            $adminFeeIdr = (int) round(($baseReimbursementIdr * self::PROGRAM_ADMIN_MARKUP_BPS) / 10000);
            $netReimbursementIdr = $baseReimbursementIdr - $adminFeeIdr;

            $clearingId = 'XPROP-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3));

            // Ledger clearing between properties:
            // Debit Loyalty Points Liability (dimiliki oleh program sentral)
            // Credit Room Revenue (properti penukaran)
            // Credit Transfer Fee Revenue (administrasi program sentral)
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_REDEEMED->value,
                description: "Inter-property award night clearing: {$issuingPropertyRef} -> {$redeemingPropertyRef} for {$pointsAmount} PTS",
                idempotencyKey: "HTL-XPROP-CLEAR-{$clearingId}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_points_liability:IDR', $baseReimbursementIdr)
                    ->credit('htl:room_revenue:IDR', $netReimbursementIdr)
                    ->credit('htl:transfer_fee_revenue:IDR', $adminFeeIdr)
                    ->toEntries('IDR'),
                referenceType: 'CROSS_PROPERTY_SETTLEMENT',
                referenceId: $clearingId,
            ));

            return [
                'clearing_id' => $clearingId,
                'issuing_property_ref' => $issuingPropertyRef,
                'redeeming_property_ref' => $redeemingPropertyRef,
                'points_amount' => $pointsAmount,
                'base_reimbursement_idr' => $baseReimbursementIdr,
                'admin_fee_idr' => $adminFeeIdr,
                'net_reimbursement_idr' => $netReimbursementIdr,
                'status' => 'CLEARED_AND_POSTED',
                'cleared_at' => now()->toIso8601String(),
            ];
        });
    }
}
