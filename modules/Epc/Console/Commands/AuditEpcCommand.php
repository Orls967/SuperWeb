<?php

namespace Modules\Epc\Console\Commands;

use Illuminate\Console\Command;
use Modules\Epc\Application\Services\EpcService;

class AuditEpcCommand extends Command
{
    protected $signature = 'epc:audit';

    protected $description = 'Audit EPC construction WBS progress, Monthly Certificates, CIP and asset capitalization';

    public function handle(EpcService $service): int
    {
        $this->info('Starting EPC Construction & Asset Capitalization Audit...');

        $results = $service->auditEpc();

        $this->line("Status: {$results['status']}");
        $this->line("Total Projects: {$results['projects_count']}");
        $this->line("Monthly Certificates: {$results['certificates_count']}");
        $this->line("Asset Capitalizations: {$results['capitalizations_count']}");
        $this->line('Active CIP in Progress: Rp '.number_format($results['total_active_cip_idr'], 0, ',', '.'));
        $this->line('Total Capitalized Assets: Rp '.number_format($results['total_capitalized_idr'], 0, ',', '.'));
        $this->line('Discrepancies: '.count($results['discrepancies']));

        if (! empty($results['discrepancies'])) {
            foreach ($results['discrepancies'] as $discrepancy) {
                $this->error(" - {$discrepancy}");
            }

            return 1;
        }

        $this->info('EPC audit PASSED with 0 discrepancy.');

        return 0;
    }
}
