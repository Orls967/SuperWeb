<?php

declare(strict_types=1);

namespace Modules\Integration\Console\Commands;

use Illuminate\Console\Command;
use Modules\Integration\Application\Services\PlatformEconomyService;

/**
 * api:audit — Fase 147 Platform Economy Quality Gate
 */
class ApiAuditCommand extends Command
{
    protected $signature = 'api:audit';

    protected $description = 'Audit Platform Economy: Developer API usage billing, embedded finance reconciliation, white label isolation';

    public function handle(PlatformEconomyService $service): int
    {
        $this->info('Auditing Platform Economy (Fase 147)...');

        $result = $service->audit();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $result['status']],
                ['Total Developers', $result['total_developers']],
                ['Total Marketplace Apps', $result['total_apps']],
                ['Total White Label Tenants', $result['total_tenants']],
                ['Total Embedded Tx', $result['total_embedded_txs']],
                ['Discrepancy Count', $result['discrepancy_count']],
            ]
        );

        if ($result['discrepancy_count'] === 0) {
            $this->info('api:audit PASSED (0 discrepancies)');

            return self::SUCCESS;
        }

        $this->error("api:audit FAILED ({$result['discrepancy_count']} discrepancies detected)");

        return self::FAILURE;
    }
}
