<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mobility\Ev\Application\Services\EvChargingService;

class AuditEvCommand extends Command
{
    protected $signature = 'ev:audit';

    protected $description = 'Perform two-source verification of EV charging stations, chargers, and completed session tariffs';

    public function handle(EvChargingService $service): int
    {
        $this->info('Starting EV Charging & Station Operations Audit...');

        $result = $service->auditEv();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Stations Checked', $result['station_count']],
                ['Chargers Checked', $result['charger_count']],
                ['Charging Sessions Checked', $result['session_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("EV audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('EV audit PASSED cleanly.');

        return 0;
    }
}
