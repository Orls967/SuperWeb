<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Hotel\Domain\Models\HotelFolio;

class TwelveLinesCrossEcosystemService
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Cross-line Medical Tourism Package (Hospital + Hotel + Logistics Shuttle)
     */
    public function bookMedicalTourismBundle(
        string $patientUserId,
        string $hospitalEncounterId,
        string $hotelReservationId,
        int $packageTotalAmountIdr
    ): array {
        // Break down package allocation: 50% Hospital, 35% Hotel, 15% Logistics
        $hospitalShare = (int) round($packageTotalAmountIdr * 0.50);
        $hotelShare = (int) round($packageTotalAmountIdr * 0.35);
        $logisticsShare = $packageTotalAmountIdr - ($hospitalShare + $hotelShare);

        if ($this->ledger) {
            $this->ledger->post(new PostingDTO(
                type: 'MEDICAL_TOURISM_SETTLEMENT',
                description: "Cross-line medical tourism bundle settlement for user {$patientUserId}",
                idempotencyKey: "med_tour_settle:{$hospitalEncounterId}:{$hotelReservationId}",
                entries: [
                    PostingEntryDTO::forCode('escrow:medical_tourism:IDR', 'IDR', -$packageTotalAmountIdr),
                    PostingEntryDTO::forCode('hsp:billing_clearing:IDR', 'IDR', $hospitalShare),
                    PostingEntryDTO::forCode('htl:settlement_clearing:IDR', 'IDR', $hotelShare),
                    PostingEntryDTO::forCode('lgx:freight_revenue', 'IDR', $logisticsShare),
                ],
                referenceType: 'cross_line_bundles',
                referenceId: (string) Str::uuid(),
            ));
        }

        return [
            'patient_user_id' => $patientUserId,
            'total_amount' => $packageTotalAmountIdr,
            'hospital_share' => $hospitalShare,
            'hotel_share' => $hotelShare,
            'logistics_share' => $logisticsShare,
            'settled' => true,
        ];
    }

    /**
     * Post Venue or Restaurant consumption directly to Hotel Room Folio
     */
    public function chargeVenueConsumptionToHotelFolio(
        string $hotelFolioId,
        string $venueTicketNumber,
        string $chargeDescription,
        int $amountIdr
    ): HotelFolio {
        $folio = HotelFolio::findOrFail($hotelFolioId);

        $folio->increment('total_addon_charges', $amountIdr);
        $folio->update([
            'outstanding_balance' => ($folio->total_room_charges + $folio->total_addon_charges) - $folio->total_paid,
        ]);

        if ($this->ledger) {
            $this->ledger->post(new PostingDTO(
                type: 'CROSS_VENUE_FOLIO_CHARGE',
                description: "Cross-line venue charge ({$venueTicketNumber}) to folio {$folio->id}: {$chargeDescription}",
                idempotencyKey: "cross_charge:{$folio->id}:".(string) Str::uuid(),
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
