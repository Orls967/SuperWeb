<?php

declare(strict_types=1);

namespace Modules\Venue\Console\Commands;

use Illuminate\Console\Command;
use Modules\Venue\Application\Services\VenueOperationsService;

class AuditVenueCommand extends Command
{
    protected $signature = 'ven:audit';

    protected $description = 'Audit layanan operasional venue: kapasitas zona, tiket masuk, reservasi meja VIP dan integritas crowd safety';

    public function handle(VenueOperationsService $service): int
    {
        $this->info('Starting Venue Operations & Crowd Safety Audit (ven:audit)...');

        $result = $service->auditVenue();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $result['status']],
                ['Total Events', $result['event_count']],
                ['Total Tickets', $result['ticket_count']],
                ['Table Bookings', $result['booking_count']],
                ['Entertainment Zones', $result['zone_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Venue audit FAILED with {$result['discrepancies']} zone over-capacity discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Venue audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
