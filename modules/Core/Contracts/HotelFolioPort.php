<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface HotelFolioPort
{
    public function chargeVenueConsumption(
        string $hotelFolioId,
        string $venueTicketNumber,
        string $chargeDescription,
        int $amountIdr
    ): object;
}
