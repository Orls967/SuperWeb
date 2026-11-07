<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Ledger;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;

/**
 * Helper penjurnalan buku besar berpasangan untuk domain Hotel.
 */
final class HotelPostingHelper
{
    public function __construct(
        private readonly ?Ledger $ledger = null
    ) {}

    /**
     * Jurnal pendapatan kamar saat Night Audit:
     * Guest ledger (- total debit tamu), Revenue/Payable (+ kredit penambahan pendapatan/kewajiban)
     */
    public function postNightAuditRoomRevenue(
        string|int $reservationId,
        int $roomChargeIdr,
        int $taxIdr,
        int $serviceChargeIdr,
        string $businessDate,
        string $idempotencyKey
    ): void {
        if (! $this->ledger) {
            return;
        }

        $totalNight = $roomChargeIdr + $taxIdr + $serviceChargeIdr;
        $entries = [
            PostingEntryDTO::forCode('htl:guest_ledger:IDR', 'IDR', -$totalNight),
            PostingEntryDTO::forCode('htl:room_revenue:IDR', 'IDR', $roomChargeIdr),
        ];

        if ($taxIdr > 0) {
            $entries[] = PostingEntryDTO::forCode('htl:tax_payable:IDR', 'IDR', $taxIdr);
        }
        if ($serviceChargeIdr > 0) {
            $entries[] = PostingEntryDTO::forCode('htl:service_charge_payable:IDR', 'IDR', $serviceChargeIdr);
        }

        $this->ledger->post(new PostingDTO(
            type: 'HOTEL_NIGHT_AUDIT_ROOM',
            description: "Room revenue night audit {$businessDate} res #{$reservationId}",
            idempotencyKey: $idempotencyKey,
            entries: $entries,
            referenceType: 'htl_reservations',
            referenceId: (string) $reservationId
        ));
    }

    /**
     * Jurnal biaya kompensasi relokasi walk:
     * Overbooking expense (- beban), clearing external (+ kas keluar/clearing)
     */
    public function postOverbookingWalkExpense(
        string|int $reservationId,
        int $walkCostIdr,
        string $idempotencyKey
    ): void {
        if (! $this->ledger) {
            return;
        }

        $this->ledger->post(new PostingDTO(
            type: 'HOTEL_OVERBOOKING_WALK',
            description: "Overbooking relocation walk compensation res #{$reservationId}",
            idempotencyKey: $idempotencyKey,
            entries: [
                PostingEntryDTO::forCode('htl:overbooking_expense:IDR', 'IDR', -$walkCostIdr),
                PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', $walkCostIdr),
            ],
            referenceType: 'htl_reservations',
            referenceId: (string) $reservationId
        ));
    }

    /**
     * Jurnal voucher service recovery:
     * Contra revenue (- beban pengurang), guest ledger (+ pengurang piutang tamu)
     */
    public function postServiceRecoveryVoucher(
        string|int $reservationId,
        int $voucherAmountIdr,
        string $idempotencyKey
    ): void {
        if (! $this->ledger) {
            return;
        }

        $this->ledger->post(new PostingDTO(
            type: 'HOTEL_SERVICE_RECOVERY',
            description: "Service recovery contra-revenue voucher res #{$reservationId}",
            idempotencyKey: $idempotencyKey,
            entries: [
                PostingEntryDTO::forCode('htl:service_recovery_contra_revenue:IDR', 'IDR', -$voucherAmountIdr),
                PostingEntryDTO::forCode('htl:guest_ledger:IDR', 'IDR', $voucherAmountIdr),
            ],
            referenceType: 'htl_reservations',
            referenceId: (string) $reservationId
        ));
    }

    /**
     * Jurnal early check-in / late checkout surcharge:
     * Guest ledger (- beban tamu), Ancillary revenue (+ pendapatan tambahan)
     */
    public function postEarlyLateSurcharge(
        string|int $reservationId,
        int $amountIdr,
        string $type,
        string $idempotencyKey
    ): void {
        if (! $this->ledger) {
            return;
        }

        $this->ledger->post(new PostingDTO(
            type: 'HOTEL_EARLY_LATE_SURCHARGE',
            description: "{$type} surcharge res #{$reservationId}",
            idempotencyKey: $idempotencyKey,
            entries: [
                PostingEntryDTO::forCode('htl:guest_ledger:IDR', 'IDR', -$amountIdr),
                PostingEntryDTO::forCode('htl:room_revenue:IDR', 'IDR', $amountIdr),
            ],
            referenceType: 'htl_reservations',
            referenceId: (string) $reservationId
        ));
    }
}
