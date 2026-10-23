<?php

declare(strict_types=1);

namespace Modules\Proptech\Console\Commands;

use Illuminate\Console\Command;
use Modules\Proptech\Application\Services\ProptechService;

class AuditProptechCommand extends Command
{
    protected $signature = 'prp:audit';

    protected $description = 'Perform two-source verification of smart building zones, sensor readings, and utility billings';

    public function handle(ProptechService $service): int
    {
        $this->info('Starting Proptech & Smart Building Operations Audit...');

        $result = $service->auditProptech();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Zones Checked', $result['zone_count']],
                ['Sensors Checked', $result['sensor_count']],
                ['Utility Billings Checked', $result['billing_count']],
                ['HVAC Commands Checked', $result['hvac_command_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Proptech audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('Proptech audit PASSED cleanly.');

        return 0;
    }
}
