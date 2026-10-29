<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Application\Services\HotelInventoryService;
use Modules\Hotel\Domain\Models\HotelDeposit;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelHousekeepingLog;
use Modules\Hotel\Domain\Models\HotelReservation;
use Modules\Hotel\Domain\Models\HotelRoom;
use Modules\Hotel\Domain\Models\HotelRoomNight;
use Modules\Hotel\Ledger\HotelTransactionTypes;
use Modules\Shared\Application\BaseAction;

class CheckOutAndSettleFolioAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null,
        protected ?HotelInventoryService $inventoryService = null
    ) {}

    /**
     * @return array{
     *     folio: HotelFolio,
     *     reservation: HotelReservation,
     *     room: HotelRoom,
     *     deposit_applied_idr: int,
     *     status: string
     * }
     */
    public function handle(
        string $folioId,
        string $paymentMethod = 'CASH',
        string $idempotencyKey = '',
        int $paymentAmountIdr = 0
    ): array {
        if (empty(trim($idempotencyKey))) {
            throw new \InvalidArgumentException('Idempotency-Key wajib disertakan.');
        }

        return DB::transaction(function () use (
            $folioId,
            $paymentMethod,
            $idempotencyKey,
            $paymentAmountIdr
        ) {
            $folio = HotelFolio::lockForUpdate()->findOrFail($folioId);
            $reservation = HotelReservation::lockForUpdate()->findOrFail($folio->reservation_id);
            $room = HotelRoom::lockForUpdate()->findOrFail($reservation->room_id);

            // 1. Terapkan deposit jika ada yang tersisa
            $deposit = HotelDeposit::where('reservation_id', $reservation->id)
                ->whereIn('status', ['HELD', 'PARTIALLY_REFUNDED'])
                ->first();

            $depositAppliedIdr = 0;
            if ($deposit && $deposit->remaining_amount_idr > 0) {
                $depositAppliedIdr = min($deposit->remaining_amount_idr, (int) $folio->outstanding_balance);
                $deposit->applied_amount_idr += $depositAppliedIdr;
                if ($deposit->remaining_amount_idr === 0) {
                    $deposit->status = 'APPLIED';
                }
                $deposit->save();

                $folio->total_paid += $depositAppliedIdr;
                $folio->outstanding_balance = max(0, (int) $folio->outstanding_balance - $depositAppliedIdr);

                // Posting penerapan deposit di ledger:
                // Debit htl:guest_deposit (mengurangi kewajiban)
                // Kredit htl:guest_ledger (mengurangi piutang tamu)
                if ($this->ledger && $depositAppliedIdr > 0) {
                    $this->ledger->post(new PostingDTO(
                        type: HotelTransactionTypes::HOTEL_FOLIO_SETTLEMENT->value,
                        description: "Penerapan deposit ke folio {$folio->id}",
                        idempotencyKey: "htl:dep_apply:{$idempotencyKey}",
                        entries: [
                            PostingEntryDTO::forCode('htl:guest_deposit:IDR', 'IDR', -$depositAppliedIdr),
                            PostingEntryDTO::forCode('htl:guest_ledger:IDR', 'IDR', $depositAppliedIdr),
                        ],
                        referenceType: 'htl_folios',
                        referenceId: $folio->id
                    ));
                }
            }

            // 2. Terapkan pembayaran kasir
            if ($paymentAmountIdr > 0) {
                $folio->total_paid += $paymentAmountIdr;
                $folio->outstanding_balance = max(0, (int) $folio->outstanding_balance - $paymentAmountIdr);

                if ($this->ledger) {
                    $this->ledger->post(new PostingDTO(
                        type: HotelTransactionTypes::HOTEL_FOLIO_SETTLEMENT->value,
                        description: "Pelunasan kasir ({$paymentMethod}) folio {$folio->id}",
                        idempotencyKey: "htl:settle_pay:{$idempotencyKey}",
                        entries: [
                            PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -$paymentAmountIdr),
                            PostingEntryDTO::forCode('htl:guest_ledger:IDR', 'IDR', $paymentAmountIdr),
                        ],
                        referenceType: 'htl_folios',
                        referenceId: $folio->id
                    ));
                }
            }

            // 3. Verifikasi saldo akhir wajib 0
            if ((int) $folio->outstanding_balance !== 0) {
                throw new \InvalidArgumentException(
                    "Check-out ditolak: folio masih memiliki saldo terutang Rp {$folio->outstanding_balance}. Tagihan harus lunas atau dialihkan ke city ledger."
                );
            }

            $folio->status = 'settled';
            $folio->save();

            $reservation->status = 'checked_out';
            $reservation->save();

            // 4. Ubah status kamar menjadi DIRTY (H-9)
            $prevStatus = $room->status;
            $room->update(['status' => 'dirty']);

            HotelHousekeepingLog::create([
                'id' => (string) Str::uuid(),
                'room_id' => $room->id,
                'from_status' => $prevStatus,
                'to_status' => 'dirty',
                'changed_by_user_id' => (int) $reservation->guest_user_id,
                'notes' => "Check-out tamu {$reservation->reservation_number}",
            ]);

            // 5. Early check-out: lepas sisa malam jika check-out lebih awal dari rencana
            $today = Carbon::now()->toDateString();
            HotelRoomNight::where('reservation_id', $reservation->id)
                ->whereDate('night_date', '>', $today)
                ->delete();

            $this->audit(
                action: 'hotel.folio.checkout_settled',
                auditable: $folio,
                oldValues: ['status' => 'open'],
                newValues: [
                    'reservation_number' => $reservation->reservation_number,
                    'room_number' => $room->room_number,
                    'total_paid' => $folio->total_paid,
                    'outstanding_balance' => 0,
                ],
                correlationId: "htl_out_{$folio->id}",
                impactType: 'hotel_checkout'
            );

            return [
                'folio' => $folio,
                'reservation' => $reservation,
                'room' => $room,
                'deposit_applied_idr' => $depositAppliedIdr,
                'status' => 'CHECKOUT_COMPLETED',
            ];
        });
    }
}
