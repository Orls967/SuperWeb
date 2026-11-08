<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Algorithms\PointsLiabilityFifo;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Models\HtlTravelPassPointBatch;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelVoucherCatalogRedemptionService
{
    public function __construct(
        protected ?PointsLiabilityFifo $fifo = null,
        protected ?LedgerService $ledgerService = null
    ) {
        $this->fifo = $fifo ?? new PointsLiabilityFifo;
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     voucher_id: string,
     *     voucher_code: string,
     *     travel_pass_id: string,
     *     catalog_item_name: string,
     *     category: string,
     *     points_redeemed: int,
     *     voucher_face_value_idr: int,
     *     valid_until: string,
     *     qr_payload: string
     * }
     */
    public function redeemCatalogVoucher(
        string $travelPassId,
        string $catalogItemCode,
        string $catalogItemName,
        string $category = 'FNB_DINING',
        int $pointsRequired = 5000,
        int $faceValueIdr = 500000
    ): array {
        if ($pointsRequired <= 0) {
            throw new RuntimeException("Poin yang dibutuhkan tidak valid: {$pointsRequired}");
        }

        return DB::transaction(function () use (
            $travelPassId,
            $catalogItemName,
            $category,
            $pointsRequired,
            $faceValueIdr
        ) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();

            if ($pass->pts_balance < $pointsRequired) {
                throw new RuntimeException("Saldo poin tidak mencukupi untuk voucher {$catalogItemName}: Saldo {$pass->pts_balance} PTS, dibutuhkan {$pointsRequired} PTS");
            }

            // Deduct FIFO
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
                ptsToRedeem: $pointsRequired,
                currentTimestamp: now()->getTimestamp()
            );

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

            $pass->decrement('pts_balance', $pointsRequired);

            $voucherId = 'VCH-'.now()->format('Ymd').'-'.bin2hex(random_bytes(4));
            $voucherCode = 'HTL-'.strtoupper($category).'-'.strtoupper(bin2hex(random_bytes(3)));
            $validUntil = now()->addMonths(3);

            // Ledger: Debit Loyalty Points Liability, Credit Clearing Payable / Merchant Settlement
            $liabilityIdr = $pointsRequired * 100;
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_REDEEMED->value,
                description: "Redemption of voucher {$catalogItemName} ({$voucherCode}) for pass {$pass->pass_number}",
                idempotencyKey: "HTL-VCH-REDEEM-{$voucherId}",
                entries: Posting::lines()
                    ->debit('htl:loyalty_points_liability:IDR', $liabilityIdr)
                    ->credit('clearing:external:IDR', $liabilityIdr)
                    ->toEntries('IDR'),
                referenceType: 'VOUCHER_REDEMPTION',
                referenceId: $voucherId,
            ));

            return [
                'voucher_id' => $voucherId,
                'voucher_code' => $voucherCode,
                'travel_pass_id' => $pass->id,
                'catalog_item_name' => $catalogItemName,
                'category' => $category,
                'points_redeemed' => $pointsRequired,
                'voucher_face_value_idr' => $faceValueIdr,
                'valid_until' => $validUntil->toIso8601String(),
                'qr_payload' => "sw://voucher/{$voucherCode}/{$voucherId}",
            ];
        });
    }
}
