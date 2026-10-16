<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Core\Contracts\HotelFolioPort;
use Modules\Hotel\Domain\Models\HotelFolio;

class HotelFolioAdapter implements HotelFolioPort
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    public function chargeVenueConsumption(
        string $hotelFolioId,
        string $venueTicketNumber,
        string $chargeDescription,
        int $amountIdr
    ): object {
        $folio = HotelFolio::findOrFail($hotelFolioId);

        $folio->increment('total_addon_charges', $amountIdr);
        $folio->update([
            'outstanding_balance' => ($folio->total_room_charges + $folio->total_addon_charges) - $folio->total_paid,
        ]);

        if ($this->ledger) {
            $this->ledger->post(new PostingDTO(
                type: 'CROSS_VENUE_FOLIO_CHARGE',
                description: "Cross-line venue charge ({$venueTicketNumber}) to folio {$folio->id}: {$chargeDescription}",
                idempotencyKey: "core:cross_charge:{$folio->id}:{$venueTicketNumber}",
                entries: [
                    PostingEntryDTO::forCode('htl:city_ledger_receivable:IDR', 'IDR', -$amountIdr),
                    PostingEntryDTO::forCode('ven:pos_revenue:IDR', 'IDR', $amountIdr),
                ],
                referenceType: 'htl_folios',
                referenceId: $folio->id,
            ));
        }

        return $folio->fresh();
    }
}
