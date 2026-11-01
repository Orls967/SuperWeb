<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Payment\Contracts\PaymentGatewayInterface;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;

class CaptureTableDepositAction
{
    public function __construct(
        private readonly Ledger $ledgerService,
        private readonly ?PaymentGatewayInterface $paymentGateway = null
    ) {}

    /**
     * @return array{success: bool, booking: EntertainmentTableBooking}
     */
    public function handle(
        int $bookingId,
        string $idempotencyKey
    ): array {
        if (trim($idempotencyKey) === '') {
            throw new \InvalidArgumentException('Idempotency key wajib diisi (U-4)');
        }

        $booking = EntertainmentTableBooking::findOrFail($bookingId);

        if ($booking->status === 'SEATED') {
            return [
                'success' => true,
                'booking' => $booking,
            ];
        }

        return DB::transaction(function () use ($booking, $idempotencyKey) {
            if ($this->paymentGateway !== null) {
                $this->paymentGateway->capture([
                    'amount' => (int) $booking->deposit_amount_idr,
                    'idempotencyKey' => $idempotencyKey,
                ]);
            }

            $booking->update(['status' => 'SEATED']);

            // Post to ledger: Debit clearing, Credit ven:vip_escrow:IDR
            $this->ledgerService->post(new PostingDTO(
                type: 'VENUE_VIP_TABLE_ESCROW',
                description: "Capture VIP table deposit for booking {$booking->booking_code}",
                idempotencyKey: $idempotencyKey,
                entries: Posting::lines()
                    ->debit('ven:ticket_clearing:IDR', (int) $booking->deposit_amount_idr)
                    ->credit('ven:vip_escrow:IDR', (int) $booking->deposit_amount_idr)
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
