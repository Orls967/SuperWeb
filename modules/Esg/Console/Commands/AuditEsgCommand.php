<?php

namespace Modules\Esg\Console\Commands;

use Illuminate\Console\Command;
use Modules\Esg\Application\Services\EsgService;

class AuditEsgCommand extends Command
{
    protected $signature = 'esg:audit';

    protected $description = 'Audit ESG greenhouse gas emissions, carbon offsets, and sustainability scores';

    public function handle(EsgService $service): int
    {
        $this->info('Starting Environmental, Social & Governance (ESG) Audit...');

        $results = $service->auditEsg();

        $this->line("Status: {$results['status']}");
        $this->line("Emissions Recorded: {$results['emissions_count']}");
        $this->line("Total CO2e Emitted: {$results['total_co2e_tons']} tons");
        $this->line("Carbon Credits: {$results['credits_count']}");
        $this->line("Total Offset Retired: {$results['total_offset_tons']} tons");
        $this->line('Discrepancies: '.count($results['discrepancies']));

        if (! empty($results['discrepancies'])) {
            foreach ($results['discrepancies'] as $discrepancy) {
                $this->error(" - {$discrepancy}");
            }

            return 1;
        }

        $this->info('ESG audit PASSED with 0 discrepancy.');

        return 0;
    }
}
