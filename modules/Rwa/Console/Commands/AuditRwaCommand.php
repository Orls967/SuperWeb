<?php

declare(strict_types=1);

namespace Modules\Rwa\Console\Commands;

use Illuminate\Console\Command;
use Modules\Rwa\Application\Services\RwaTokenService;

class AuditRwaCommand extends Command
{
    protected $signature = 'rwa:audit';

    protected $description = 'Perform two-source verification of RWA asset tokens, allocations, and dividend conservation';

    public function handle(RwaTokenService $service): int
    {
        $this->info('Starting Real-World Asset (RWA) Tokenization Audit...');

        $result = $service->auditRwa();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Audit Status', $result['status']],
                ['RWA Assets Checked', $result['asset_count']],
                ['Token Holdings Checked', $result['holding_count']],
                ['Dividend Distributions Checked', $result['dividend_count']],
                ['Discrepancies', $result['discrepancies']],
            ]
        );

        if ($result['discrepancies'] > 0) {
            $this->error("RWA audit FAILED with {$result['discrepancies']} discrepancies found.");

            return 1;
        }

        $this->info('RWA audit PASSED cleanly.');

        return 0;
    }
}
