<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Services\MiningFleetDispatchService;

class AuditMiningCommand extends Command
{
    protected $signature = 'min:audit';

    protected $aliases = ['mining:audit'];

    protected $description = 'Perform two-source verification of mining equipment statuses and dispatch runs';

    public function handle(MiningFleetDispatchService $service): int
    {
        $this->info('Starting Mining Operations & Fleet Audit...');

        $result = $service->auditMining();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Sites Checked', $result['site_count']],
                ['Pits Checked', $result['pit_count']],
                ['Equipment Fleet Checked', $result['equipment_count']],
                ['Dispatch Runs Checked', $result['dispatch_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Mining audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('Mining audit PASSED cleanly.');

        return 0;
    }
}
