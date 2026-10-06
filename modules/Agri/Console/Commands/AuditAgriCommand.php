<?php

namespace Modules\Agri\Console\Commands;

use Illuminate\Console\Command;
use Modules\Agri\Application\Services\AgriService;

class AuditAgriCommand extends Command
{
    protected $signature = 'agri:audit';

    protected $description = 'Audit contract farming collections, grading payouts, and advance deductions';

    public function handle(AgriService $service): int
    {
        $this->info('Starting Agribusiness & Contract Farming Audit...');

        $results = $service->auditAgri();

        $this->line("Status: {$results['status']}");
        $this->line("Registered Farmers: {$results['farmers_count']}");
        $this->line("Active Farming Contracts: {$results['contracts_count']}");
        $this->line("Collection Batches: {$results['harvest_batches_count']}");
        $this->line("Total Harvest Volume: {$results['total_harvest_kg']} kg");
        $this->line('Total Net Payout: Rp '.number_format($results['total_net_payout_idr'], 0, ',', '.'));
        $this->line('Discrepancies: '.count($results['discrepancies']));

        if (! empty($results['discrepancies'])) {
            foreach ($results['discrepancies'] as $discrepancy) {
                $this->error(" - {$discrepancy}");
            }

            return 1;
        }

        $this->info('Agribusiness audit PASSED with 0 discrepancy.');

        return 0;
    }
}
