<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Adapters;

/**
 * Adaptor Integrasi: Pemesanan Tiket Masuk & VIP Pass Venue/Festival (Fase 89/90/116).
 */
final class HotelVenueTicketBundleAdapter
{
    /**
     * @return array{booking_ref: string, venue_event_id: string, ticket_count: int, status: string}
     */
    public function reserveVenuePackageTickets(
        string $venueEventId,
        string $guestName,
        int $ticketCount
    ): array {
        $bookingRef = 'VEN-PKG-'.date('Ymd').'-'.substr(md5($venueEventId.$guestName), 0, 8);

        return [
            'booking_ref' => $bookingRef,
            'venue_event_id' => $venueEventId,
            'ticket_count' => $ticketCount,
            'status' => 'RESERVED_AND_ESCROW_HELD',
        ];
    }
}
