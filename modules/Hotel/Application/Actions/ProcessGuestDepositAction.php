<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Shared\Application\BaseAction;

class ProcessGuestDepositAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * @return array{deposit: HotelDeposit, status: string}
     */
    public function execute(
        string $propertyId,
        string $reservationId,
        string $guestUserId,
        int $amountIdr,
        string $paymentMethod,
        string $idempotencyKey
    ): HotelDeposit {
        $result = $this->handle($propertyId, $reservationId, $guestUserId, $amountIdr, $paymentMethod, $idempotencyKey);

        return $result['deposit'];
    }

    public function handle(
        string $propertyId,
        string $reservationId,
        string $guestUserId,
        int $amountIdr,
        string $paymentMethod,
        string $idempotencyKey
    ): array {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency-Key wajib disertakan.');
        }

        if ($amountIdr <= 0) {
            throw new \InvalidArgumentException('Nominal deposit harus lebih besar dari 0.');
        }

        // Idempotency check: jika key sudah terdaftar, return deposit yang sama
        $existing = HotelDeposit::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return [
                'deposit' => $existing,
                'status' => 'ALREADY_PROCESSED',
            ];
        }

        return DB::transaction(function () use (
            $propertyId,
            $reservationId,
            $guestUserId,
            $amountIdr,
            $paymentMethod,
            $idempotencyKey
        ) {
            $reservation = HotelReservation::lockForUpdate()->findOrFail($reservationId);
            $depositId = (string) Str::uuid();

            if ($this->ledger) {
                $this->ledger->post(new PostingDTO(
                    type: HotelTransactionTypes::HOTEL_GUEST_DEPOSIT->value,
                    description: "Penerimaan deposit tamu reservasi {$reservation->reservation_number}",
                    idempotencyKey: "htl:dep:{$idempotencyKey}",
                    entries: [
                        PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -$amountIdr),
                        PostingEntryDTO::forCode('htl:guest_deposit:IDR', 'IDR', $amountIdr),
                    ],
                    referenceType: 'htl_deposits',
                    referenceId: $depositId
                ));
            }

            $deposit = HotelDeposit::create([
                'id' => $depositId,
                'property_id' => $propertyId,
                'reservation_id' => $reservation->id,
                'guest_user_id' => (int) $guestUserId,
                'amount_idr' => $amountIdr,
                'applied_amount_idr' => 0,
                'refunded_amount_idr' => 0,
                'payment_method' => strtoupper($paymentMethod),
                'status' => 'HELD',
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($reservation->status === 'tentatif') {
                $reservation->update(['status' => 'confirmed']);
            }

            $this->audit(
                action: 'hotel.deposit.received',
                auditable: $deposit,
                oldValues: null,
                newValues: [
                    'reservation_number' => $reservation->reservation_number,
                    'amount_idr' => $amountIdr,
                    'payment_method' => $paymentMethod,
                    'idempotency_key' => $idempotencyKey,
                ],
                correlationId: "htl_dep_{$deposit->id}",
                impactType: 'hotel_deposit'
            );

            $this->outbox(
                eventType: 'HotelGuestDepositReceived',
                payload: [
                    'deposit_id' => $deposit->id,
                    'reservation_id' => $reservation->id,
                    'amount_idr' => $amountIdr,
                ],
                idempotencyKey: $idempotencyKey
            );

            return [
                'deposit' => $deposit,
                'status' => 'DEPOSIT_HELD',
            ];
        });
    }
}
