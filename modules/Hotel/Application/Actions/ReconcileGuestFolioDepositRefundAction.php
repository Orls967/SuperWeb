<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;

/**
 * Tindakan Pengembalian Sisa Deposit Tamu Saat Check-Out (Deposit Refund).
 */
final class ReconcileGuestFolioDepositRefundAction
{
    public function __construct(
        private readonly ?Ledger $ledger = null
    ) {}

    /**
     * @return array{folio_id: string, refunded_amount_idr: int, status: string}
     */
    public function execute(string|int $folioId, int $refundAmountIdr, string $idempotencyKey): array
    {
        $folio = HotelFolio::findOrFail((string) $folioId);

        if ($refundAmountIdr <= 0) {
            abort(422, 'Jumlah pengembalian deposit harus bernilai positif.');
        }

        if ($this->ledger) {
            $this->ledger->post(new PostingDTO(
                type: 'HOTEL_DEPOSIT_REFUND',
                description: "Refund kelebihan deposit folio #{$folio->id}",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forCode('htl:guest_deposit:IDR', 'IDR', -$refundAmountIdr),
                    PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', $refundAmountIdr),
                ],
                referenceType: 'htl_folios',
                referenceId: (string) $folio->id
            ));
        }

        HotelFolioLine::create([
            'folio_id' => $folio->id,
            'business_date' => date('Y-m-d'),
            'line_type' => 'DEPOSIT_REFUND',
            'description' => 'Pengembalian Sisa Deposit Tamu',
            'amount_idr' => $refundAmountIdr,
            'base_amount_idr' => $refundAmountIdr,
            'service_charge_idr' => 0,
            'tax_pbjt_idr' => 0,
        ]);

        return [
            'folio_id' => (string) $folio->id,
            'refunded_amount_idr' => $refundAmountIdr,
            'status' => 'REFUNDED',
        ];
    }
}
