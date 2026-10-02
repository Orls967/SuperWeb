<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Exceptions\ClaimException;
use Modules\Logistics\Domain\Models\Claim;

class PayClaimAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger
    ) {}

    /**
     * Alur 11 (langkah 4): bayar klaim yang disetujui ke dompet shipper. Debit beban klaim, kredit dompet.
     * Anti bayar ganda: status harus approved (dikunci), kunci jurnal idempoten, dan status paid terminal.
     */
    public function execute(User $payer, Claim $claim): Claim
    {
        if (! ($payer->isAdmin() || $payer->isLogisticsAdmin())) {
            throw ClaimException::forbidden('membayarkan klaim');
        }

        return DB::transaction(function () use ($payer, $claim) {
            $claim = Claim::whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if ($claim->status !== Claim::STATUS_APPROVED) {
                throw ClaimException::invalidState($claim->status, Claim::STATUS_APPROVED);
            }

            $amount = (int) $claim->approved_amount_idr;
            $shipment = $claim->shipment;
            $wallet = $shipment->shipper->walletAccount('IDR');

            $this->ledger->post(
                type: TransactionType::LOGISTICS_CLAIM_PAYOUT,
                description: "Pembayaran klaim {$claim->claim_number} resi {$shipment->tracking_number}",
                idempotencyKey: "lgx:claim_pay:{$claim->id}",
                entries: [
                    [LogisticsLedger::CLAIMS_EXPENSE, -$amount],
                    [$wallet->id, $amount],
                ],
                referenceType: Claim::class,
                referenceId: $claim->id,
                createdBy: $payer->id,
            );

            $claim->update([
                'status' => Claim::STATUS_PAID,
                'paid_amount_idr' => $amount,
                'paid_by' => $payer->id,
                'paid_at' => now(),
            ]);

            return $claim;
        });
    }
}
