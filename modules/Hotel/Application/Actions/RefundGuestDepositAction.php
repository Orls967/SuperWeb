<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Shared\Application\BaseAction;

class RefundGuestDepositAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * @return array{deposit: HotelDeposit, refunded_amount_idr: int, remaining_amount_idr: int}
     */
    public function handle(
        string $depositId,
        int $refundAmountIdr,
        string $idempotencyKey
    ): array {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency-Key wajib disertakan.');
        }

        if ($refundAmountIdr <= 0) {
            throw new \InvalidArgumentException('Nominal refund harus lebih besar dari 0.');
        }

        return DB::transaction(function () use ($depositId, $refundAmountIdr, $idempotencyKey) {
            $deposit = HotelDeposit::lockForUpdate()->findOrFail($depositId);
            $remaining = $deposit->remaining_amount_idr;

            if ($refundAmountIdr > $remaining) {
                throw new \InvalidArgumentException(
                    "Nominal refund (Rp {$refundAmountIdr}) melebihi sisa deposit yang tersedia (Rp {$remaining})."
                );
            }

            if ($this->ledger) {
                $this->ledger->post(new PostingDTO(
                    type: HotelTransactionTypes::HOTEL_DEPOSIT_REFUND->value,
                    description: "Pengembalian sisa deposit tamu deposit {$deposit->id}",
                    idempotencyKey: "htl:ref:{$idempotencyKey}",
                    entries: [
                        PostingEntryDTO::forCode('htl:guest_deposit:IDR', 'IDR', -$refundAmountIdr),
                        PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', $refundAmountIdr),
                    ],
                    referenceType: 'htl_deposits',
                    referenceId: $deposit->id
                ));
            }

            $deposit->refunded_amount_idr += $refundAmountIdr;
            $newRemaining = $deposit->remaining_amount_idr;
            $deposit->status = $newRemaining === 0 ? 'REFUNDED' : 'PARTIALLY_REFUNDED';
            $deposit->save();

            $this->audit(
                action: 'hotel.deposit.refunded',
                auditable: $deposit,
                oldValues: ['remaining' => $remaining],
                newValues: [
                    'refunded_amount_idr' => $refundAmountIdr,
                    'remaining_amount_idr' => $newRemaining,
                    'idempotency_key' => $idempotencyKey,
                ],
                correlationId: "htl_ref_{$deposit->id}",
                impactType: 'hotel_deposit_refund'
            );

            $this->outbox(
                eventType: 'HotelGuestDepositRefunded',
                payload: [
                    'deposit_id' => $deposit->id,
                    'refunded_amount_idr' => $refundAmountIdr,
                    'remaining_amount_idr' => $newRemaining,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'deposit' => $deposit,
                'refunded_amount_idr' => $refundAmountIdr,
                'remaining_amount_idr' => $newRemaining,
            ];
        });
    }
}
