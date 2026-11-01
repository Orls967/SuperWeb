<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Venue\Application\Services\VenuePaymentGateway;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;

class ForfeitTableNoShowDepositAction
{
    public function __construct(
        private readonly Ledger $ledgerService,
        private readonly ?VenuePaymentGateway $paymentGateway = null
    ) {}

    /**
     * @return array{success: bool, booking: EntertainmentTableBooking}
     */
    public function handle(
        int $bookingId,
        string $reason,
        string $idempotencyKey
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        $booking = EntertainmentTableBooking::findOrFail($bookingId);

        if ($booking->status === 'NO_SHOW') {
            return [
                'success' => true,
                'booking' => $booking,
            ];
        }

        return DB::transaction(function () use ($booking, $reason, $idempotencyKey) {
            if ($this->paymentGateway !== null) {
                $this->paymentGateway->capture([
                    'amount' => (int) $booking->deposit_amount_idr,
                    'idempotencyKey' => $idempotencyKey,
                ]);
            }

            $booking->update(['status' => 'NO_SHOW']);

            // Post to ledger: Debit clearing, Credit ven:vip_noshow_rev:IDR
            $this->ledgerService->post(new PostingDTO(
                type: 'VENUE_TABLE_NO_SHOW_FEE',
                description: "Forfeit VIP table deposit no-show for booking {$booking->booking_code}: {$reason}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ven:ticket_clearing:IDR', (int) $booking->deposit_amount_idr)
                    ->credit('ven:vip_noshow_rev:IDR', (int) $booking->deposit_amount_idr)
                    ->toEntries('IDR'),
                referenceType: 'TABLE_BOOKING',
                referenceId: (string) $booking->id,
            ));

            return [
                'success' => true,
                'booking' => $booking,
            ];
        });
    }
}
