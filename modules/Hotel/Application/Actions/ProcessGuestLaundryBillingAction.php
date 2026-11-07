<?php

declare(strict_types=1);

namespace Modules\Hotel\Application\Actions;

use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Services\HotelMinibarAndLaundryPostingService;

/**
 * Tindakan Pembebanan Tagihan Binatu (Guest Laundry Express & Regular).
 */
final class ProcessGuestLaundryBillingAction
{
    public function __construct(
        private readonly HotelMinibarAndLaundryPostingService $postingService
    ) {}

    /**
     * @return array{laundry_ticket_id: string, billed_amount_idr: int, is_express: bool}
     */
    public function execute(
        string|int $reservationId,
        string $ticketCode,
        int $laundrySubtotalIdr,
        bool $isExpress = false
    ): array {
        $folio = HotelFolio::where('reservation_id', (string) $reservationId)->firstOrFail();

        $finalBase = $isExpress ? (int) round($laundrySubtotalIdr * 1.50) : $laundrySubtotalIdr;
        $desc = 'Laundry '.($isExpress ? 'Express (+50%): ' : 'Regular: ')."#{$ticketCode}";

        $res = $this->postingService->postIncidentalCharge(
            folioId: $folio->id,
            incidentalType: 'LAUNDRY',
            baseAmountIdr: $finalBase,
            itemSummary: $desc
        );

        return [
            'laundry_ticket_id' => $ticketCode,
            'billed_amount_idr' => $res['total_charged_idr'],
            'is_express' => $isExpress,
        ];
    }
}
