<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Mining\Domain\Models\MiningRoyaltyReturn;
use Modules\Mining\Ledger\MiningAccounts;
use Modules\Mining\Ledger\MiningTransactionTypes;
use Modules\Shared\Application\BaseAction;

class FinalizeRoyaltyReturnAction extends BaseAction
{
    public function __construct(
        private readonly CalculateMonthlyRoyaltyAction $royaltyCalc,
        private readonly LedgerService $ledgerService
    ) {}

    /**
     * @return object{royaltyReturn: MiningRoyaltyReturn}
     */
    public function handle(
        string $royaltyReturnId,
        int $finalPriceUsdCentsPerTon,
        int $finalExchangeRateIdr1e6,
        string $idempotencyKey = ''
    ): object {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency key wajib diisi.');
        }

        return DB::transaction(function () use (
            $royaltyReturnId,
            $finalPriceUsdCentsPerTon,
            $finalExchangeRateIdr1e6,
            $idempotencyKey
        ) {
            $return = MiningRoyaltyReturn::lockForUpdate()->findOrFail($royaltyReturnId);

            if ($return->status === 'FINALIZED') {
                return (object) ['royaltyReturn' => $return];
            }

            $finalRoyaltyIdr = $this->royaltyCalc->computeRoyalty(
                (int) $return->total_production_kg,
                $finalPriceUsdCentsPerTon,
                $finalExchangeRateIdr1e6,
                (int) $return->royalty_rate_bps
            );

            $deltaIdr = $finalRoyaltyIdr - (int) $return->calculated_royalty_idr;

            $return->finalized_royalty_idr = $finalRoyaltyIdr;
            $return->delta_royalty_idr = $deltaIdr;
            $return->status = 'FINALIZED';
            $return->save();

            // Post delta to ledger (append-only, without altering previous entries) (T-6)
            if ($deltaIdr !== 0) {
                $lines = Posting::lines();
                if ($deltaIdr > 0) {
                    $lines->debit(MiningAccounts::royaltyExpense(), $deltaIdr)
                        ->credit(MiningAccounts::royaltyPayable(), $deltaIdr);
                } else {
                    $lines->debit(MiningAccounts::royaltyPayable(), abs($deltaIdr))
                        ->credit(MiningAccounts::royaltyExpense(), abs($deltaIdr));
                }

                $this->ledgerService->post(new PostingDTO(
                    type: MiningTransactionTypes::MINING_ROYALTY_FINALIZATION->value,
                    description: "Penyesuaian finalisasi royalti Minerba {$return->commodity} {$return->period_local} (Delta: {$deltaIdr} IDR)",
                    idempotencyKey: "min:roy:final:{$return->id}:{$idempotencyKey}",
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'MINING_ROYALTY_FINALIZATION',
                    referenceId: $return->id,
                ));
            }

            return (object) ['royaltyReturn' => $return];
        });
    }
}
