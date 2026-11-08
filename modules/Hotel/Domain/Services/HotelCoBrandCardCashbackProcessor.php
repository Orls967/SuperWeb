<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use RuntimeException;

final class HotelCoBrandCardCashbackProcessor
{
    public const DEFAULT_BANK_FEE_BPS = 150; // 1.5%

    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * @return array{
     *     cashback_id: string,
     *     travel_pass_id: string,
     *     transaction_amount_idr: int,
     *     cashback_points: int,
     *     cashback_value_idr: int,
     *     bank_subsidy_idr: int,
     *     hotel_cost_idr: int,
     *     status: string
     * }
     */
    public function processCardSpendCashback(
        string $travelPassId,
        string $bankCode,
        int $spendAmountIdr,
        string $cardTier = 'PLATINUM',
        string $merchantCategory = 'HOTEL_DIRECT'
    ): array {
        if ($spendAmountIdr <= 0) {
            throw new RuntimeException("Nilai transaksi kartu harus positif: {$spendAmountIdr}");
        }

        return DB::transaction(function () use (
            $travelPassId,
            $bankCode,
            $spendAmountIdr,
            $cardTier,
            $merchantCategory
        ) {
            $pass = HtlTravelPass::where('id', $travelPassId)->lockForUpdate()->firstOrFail();

            // Multiplier based on card tier and merchant category
            $multiplier = match ($cardTier) {
                'BLACK', 'INFINITE' => 0.05, // 5% rebate
                'SIGNATURE', 'PLATINUM' => 0.03, // 3% rebate
                default => 0.015, // 1.5% rebate
            };

            if ($merchantCategory === 'HOTEL_DIRECT') {
                $multiplier += 0.02; // +2% bonus for direct property spend
            }

            $cashbackValueIdr = (int) round($spendAmountIdr * $multiplier);
            $cashbackPoints = (int) round($cashbackValueIdr / 100); // 100 IDR per point

            // Co-funding split: Bank pays 60%, Hotel pays 40%
            $bankSubsidyIdr = (int) round($cashbackValueIdr * 0.60);
            $hotelCostIdr = $cashbackValueIdr - $bankSubsidyIdr;

            // Credit points to travel pass
            $pass->increment('pts_balance', $cashbackPoints);
            $pass->refreshTier();

            $cashbackId = 'CB-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4));

            // Post to ledger:
            // Debit Bank Receivable (bank portion) & Loyalty Expense (hotel portion)
            // Credit Loyalty Points Liability
            $this->ledgerService->post(new PostingDTO(
                type: HotelTransactionTypes::TRAVEL_PASS_PTS_EARNED->value,
                description: "Co-brand card cashback from {$bankCode} ({$cardTier}) for spend IDR ".number_format($spendAmountIdr),
                idempotencyKey: "HTL-COBRAND-CB-{$cashbackId}",
                entries: Posting::lines()
                    ->debit('clearing:external:IDR', $bankSubsidyIdr)
                    ->debit('htl:loyalty_program_expense:IDR', $hotelCostIdr)
                    ->credit('htl:loyalty_points_liability:IDR', $cashbackValueIdr)
                    ->toEntries('IDR'),
                referenceType: 'CO_BRAND_CASHBACK',
                referenceId: $cashbackId,
            ));

            return [
                'cashback_id' => $cashbackId,
                'travel_pass_id' => $pass->id,
                'transaction_amount_idr' => $spendAmountIdr,
                'cashback_points' => $cashbackPoints,
                'cashback_value_idr' => $cashbackValueIdr,
                'bank_subsidy_idr' => $bankSubsidyIdr,
                'hotel_cost_idr' => $hotelCostIdr,
                'status' => 'SETTLED',
            ];
        });
    }
}
