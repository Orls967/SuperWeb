<?php

declare(strict_types=1);

namespace Modules\Proptech\Console\Commands;

use Illuminate\Console\Command;
use Modules\Proptech\Application\Services\ProptechService;

class AuditSmartBuildingCommand extends Command
{
    protected $signature = 'prp:audit-smartbuilding';

    protected $description = 'Perform two-source verification of smart building zones, sensor readings, and utility billings';

    public function handle(ProptechService $service): int
    {
        $this->info('Starting Proptech & Smart Building Operations Audit...');

        $result = $service->auditSmartBuilding();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Zones Checked', $result['zone_count']],
                ['Sensors Checked', $result['sensor_count']],
                ['Utility Billings Checked', $result['billing_count']],
                ['HVAC Commands Checked', $result['hvac_command_count']],
                ['Discrepancies', $result['discrepancies']],
                ['Corrupted IDs', implode(', ', $result['corrupted_ids'] ?? [])],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Proptech smart building audit FAILED with {$result['discrepancies']} discrepancies found.");
            if (! empty($result['corrupted_ids'])) {
                $this->error('Corrupted record IDs: '.implode(', ', $result['corrupted_ids']));
            }

            return Command::FAILURE;
        }

        $this->info('Proptech smart building audit PASSED cleanly.');

        return Command::SUCCESS;
    }
}
