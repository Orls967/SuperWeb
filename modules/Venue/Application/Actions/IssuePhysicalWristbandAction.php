<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Modules\Venue\Application\Services\PhysicalWristbandFulfillmentService;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\VenueWristband;

final class IssuePhysicalWristbandAction
{
    public function __construct(
        private readonly PhysicalWristbandFulfillmentService $fulfillmentService
    ) {}

    public function execute(int $ticketId, string $wristbandUid): VenueWristband
    {
        /** @var EntertainmentTicket $ticket */
        $ticket = EntertainmentTicket::findOrFail($ticketId);

        return $this->fulfillmentService->pairWristband($ticket, $wristbandUid);
    }
}
