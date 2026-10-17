<?php

declare(strict_types=1);

namespace Modules\Wealth\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wealth\Application\Services\RoboAdvisorService;

class AuditWealthCommand extends Command
{
    protected $signature = 'wm:audit';

    protected $description = 'Perform two-source verification of Robo-Advisor profiles, portfolio allocations, and emergency fund guardrails';

    public function handle(RoboAdvisorService $service): int
    {
        $this->info('Starting Robo-Advisor & Wealth Management Operations Audit...');

        $result = $service->auditWealth();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Profiles Checked', $result['profile_count']],
                ['Investment Plans Checked', $result['plan_count']],
                ['Holdings Checked', $result['holding_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Wealth management audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('Wealth management audit PASSED cleanly.');

        return 0;
    }
}
