<?php

namespace Modules\B2b\Console\Commands;

use Illuminate\Console\Command;
use Modules\B2b\Application\Services\B2bService;

class AuditB2bCommand extends Command
{
    protected $signature = 'b2b:audit';

    protected $description = 'Audit B2B marketplace catalogs, auctions, and multi-party escrow balances';

    public function handle(B2bService $service): int
    {
        $this->info('Starting B2B Marketplace & Escrow Audit...');

        $results = $service->auditB2b();

        $this->line("Status: {$results['status']}");
        $this->line("Wholesale Catalog Items: {$results['catalog_items_count']}");
        $this->line("Active RFQs: {$results['active_rfqs_count']}");
        $this->line("Active Auctions: {$results['active_auctions_count']}");
        $this->line('Total Escrow Held: Rp '.number_format($results['total_escrow_held_idr'], 0, ',', '.'));
        $this->line('Discrepancies: '.count($results['discrepancies']));

        if (! empty($results['discrepancies'])) {
            foreach ($results['discrepancies'] as $discrepancy) {
                $this->error(" - {$discrepancy}");
            }

            return 1;
        }

        $this->info('B2B audit PASSED with 0 discrepancy.');

        return 0;
    }
}
