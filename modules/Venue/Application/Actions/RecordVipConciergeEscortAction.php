<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Venue\Application\Services\VipGuestConciergeService;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;

final class RecordVipConciergeEscortAction
{
    public function __construct(
        private readonly VipGuestConciergeService $conciergeService
    ) {}

    /**
     * @return array{
     *     booking_code: string,
     *     assigned_host: string,
     *     valet_spot_reserved: string,
     *     champagne_chilled: bool,
     *     welcome_amenities: list<string>,
     *     status: string
     * }
     */
    public function execute(int $bookingId, string $conciergeHostName): array
    {
        /** @var EntertainmentTableBooking $booking */
        $booking = EntertainmentTableBooking::findOrFail($bookingId);

        return $this->conciergeService->prepareVipArrival($booking, $conciergeHostName);
    }
}
