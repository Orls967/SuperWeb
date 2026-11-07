<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelFolioLine;

/**
 * Tindakan Pelunasan Tagihan City Ledger Korporat Pasca Check-Out.
 */
final class SettleCorporateCityLedgerInvoiceAction
{
    public function __construct(
        private readonly ?Ledger $ledger = null
    ) {}

    /**
     * @return array{invoice_id: string, settled_amount_idr: int, ledger_posted: bool}
     */
    public function execute(
        string|int $folioId,
        string $corporateCode,
        int $paymentAmountIdr,
        string $idempotencyKey
    ): array {
        $folio = HotelFolio::findOrFail((string) $folioId);

        if ($this->ledger) {
            $this->ledger->post(new PostingDTO(
                type: 'HOTEL_FOLIO_SETTLEMENT',
                description: "Corporate City Ledger Settlement: {$corporateCode} for Folio #{$folio->id}",
                idempotencyKey: $idempotencyKey,
                entries: [
                    PostingEntryDTO::forCode('clearing:external:IDR', 'IDR', -$paymentAmountIdr),
                    PostingEntryDTO::forCode('htl:city_ledger:IDR', 'IDR', $paymentAmountIdr),
                ],
                referenceType: 'htl_folios',
                referenceId: (string) $folio->id
            ));
        }

        HotelFolioLine::create([
            'folio_id' => $folio->id,
            'business_date' => date('Y-m-d'),
            'line_type' => 'CITY_LEDGER_PAYMENT',
            'description' => "Pelunasan Tagihan Korporat {$corporateCode}",
            'amount_idr' => -$paymentAmountIdr,
            'base_amount_idr' => -$paymentAmountIdr,
            'service_charge_idr' => 0,
            'tax_pbjt_idr' => 0,
        ]);

        return [
            'invoice_id' => "INV-CORP-{$corporateCode}-{$folio->id}",
            'settled_amount_idr' => $paymentAmountIdr,
            'ledger_posted' => true,
        ];
    }
}
