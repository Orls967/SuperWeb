<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Services\HotelOperationsService;

class AuditHotelCommand extends Command
{
    protected $signature = 'htl:audit';

    protected $description = 'Audit operasional hotel: kamar, reservasi, folio, dan integritas occupancy vs reservation';

    public function handle(HotelOperationsService $service): int
    {
        $this->info('Starting Hotel Operations Audit (htl:audit)...');

        $result = $service->auditHotel();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $result['status']],
                ['Properties', $result['property_count']],
                ['Rooms', $result['room_count']],
                ['Reservations', $result['reservation_count']],
                ['Folios', $result['folio_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Hotel audit FAILED with {$result['discrepancies']} occupancy discrepancies detected!");

            return self::FAILURE;
        }

        $this->info('Hotel audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
