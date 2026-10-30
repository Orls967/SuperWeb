<?php

declare(strict_types=1);

namespace Modules\Insurance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Insurance\Application\Services\InsurTechClaimsService;

class AuditInsuranceCommand extends Command
{
    protected $signature = 'insurance:audit';

    protected $description = 'Perform two-source verification of InsurTech products, policies, and autopilot claim payouts';

    public function handle(InsurTechClaimsService $service): int
    {
        $this->info('Starting InsurTech & Claims Autopilot Operations Audit...');

        $result = $service->auditInsurance();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['Products Checked', $result['product_count']],
                ['Policies Checked', $result['policy_count']],
                ['Claims Checked', $result['claim_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("Insurance audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('Insurance audit PASSED cleanly.');

        return 0;
    }
}
