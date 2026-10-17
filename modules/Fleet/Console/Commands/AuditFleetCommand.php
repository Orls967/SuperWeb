<?php

declare(strict_types=1);

namespace Modules\Fleet\Console\Commands;

use Illuminate\Console\Command;
use Modules\Fleet\Application\Services\FleetLeasingService;

class AuditFleetCommand extends Command
{
    protected $signature = 'flt:audit';

    protected $description = 'Perform two-source verification of B2B fleet leasing contracts, units, and amortization integrity';

    public function handle(FleetLeasingService $service): int
    {
        $this->info('Starting B2B Fleet Leasing Operations Audit...');

        $result = $service->auditFleet();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Contracts Checked', $result['contract_count']],
                ['Contract Units Checked', $result['unit_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Fleet audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('Fleet audit PASSED cleanly.');

        return 0;
    }
}
