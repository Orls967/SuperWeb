<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerTransaction;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\ArtistContract;

class ExecuteArtistContractEscrowAndWhtPayoutAction extends BaseAction
{
    public function __construct(
        private readonly ?Ledger $ledgerService = null
    ) {}

    /**
     * Settle artist contract revenue escrow with Indonesian Withholding Tax (PPh 23 bps).
     *
     * @return array{contract: ArtistContract, gross_share_idr: int, wht_tax_idr: int, advance_deducted_idr: int, net_payout_idr: int}
     */
    public function handle(
        string $artistContractId,
        int $totalDoorSalesIdr,
        int $whtBps = 200,
        string $idempotencyKey = ''
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        return DB::transaction(function () use ($artistContractId, $totalDoorSalesIdr, $whtBps, $idempotencyKey) {
            $contract = ArtistContract::lockForUpdate()->findOrFail($artistContractId);

            if ($contract->status === 'settled') {
                $existingTx = LedgerTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existingTx !== null) {
                    $gross = (int) $contract->door_share_gross;
                    $tax = intdiv($gross * $whtBps, 10000);

                    return [
                        'contract' => $contract,
                        'gross_share_idr' => $gross,
                        'wht_tax_idr' => $tax,
                        'advance_deducted_idr' => (int) $contract->advance_amount,
                        'net_payout_idr' => (int) $contract->net_payout_amount,
                    ];
                }

                throw new \RuntimeException('Kontrak artis sudah diselesaikan (settled)');
            }

            // Integer math bps (U-1)
            $doorShareBps = (int) round(((float) $contract->door_share_percentage) * 100);
            $grossShareIdr = intdiv($totalDoorSalesIdr * $doorShareBps, 10000);
            $whtTaxIdr = intdiv($grossShareIdr * $whtBps, 10000);
            $advanceDeductedIdr = (int) $contract->advance_amount;
            $netPayoutIdr = max(0, $grossShareIdr - $whtTaxIdr - $advanceDeductedIdr);

            $contract->total_door_sales = $totalDoorSalesIdr;
            $contract->door_share_gross = $grossShareIdr;
            $contract->net_payout_amount = $netPayoutIdr;
            $contract->status = 'settled';
            $contract->save();

            // Post double-entry journal to ledger
            if ($this->ledgerService !== null) {
                $artistNetPayable = $grossShareIdr - $whtTaxIdr;

                $this->ledgerService->post(new PostingDTO(
                    type: 'VENUE_ARTIST_SETTLEMENT',
                    description: "Artist fee settlement for {$contract->artist_name} ({$contract->contract_number})",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('expense:venue_artist_fee:IDR', $grossShareIdr)
                        ->credit('tax:wht23_payable:IDR', $whtTaxIdr)
                        ->credit('artist:payable:default:IDR', $artistNetPayable)
                        ->toEntries('IDR'),
                    referenceType: 'ARTIST_CONTRACT',
                    referenceId: (string) $contract->id,
                ));
            }

            return [
                'contract' => $contract,
                'gross_share_idr' => $grossShareIdr,
                'wht_tax_idr' => $whtTaxIdr,
                'advance_deducted_idr' => $advanceDeductedIdr,
                'net_payout_idr' => $netPayoutIdr,
            ];
        });
    }
}
