<?php

declare(strict_types=1);

namespace Modules\International\Console\Commands;

use Illuminate\Console\Command;
use Modules\International\Application\Services\InternationalService;

class AuditInternationalCommand extends Command
{
    protected $signature = 'intl:audit';

    protected $description = 'Audit international cooperation (JVs, licenses, OEM contracts, tech transfers, and treaties)';

    public function handle(InternationalService $service): int
    {
        $this->info('Starting International Cooperation Audit...');

        $res = $service->auditInternational();

        $this->line("Status: {$res['status']}");
        $this->line("Foreign Entities: {$res['entity_count']}");
        $this->line("Joint Ventures: {$res['jv_count']}");
        $this->line("Technology Licenses: {$res['license_count']}");
        $this->line("OEM Contracts: {$res['oem_count']}");
        $this->line("Tech Transfers: {$res['tech_transfer_count']}");
        $this->line("Discrepancies: {$res['discrepancy_count']}");

        if ($res['discrepancy_count'] > 0) {
            $this->error('International cooperation audit FAILED: Invariant violation detected!');

            return self::FAILURE;
        }

        $this->info('International cooperation audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
