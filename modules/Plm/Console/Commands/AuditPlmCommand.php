<?php

declare(strict_types=1);

namespace Modules\Plm\Console\Commands;

use Illuminate\Console\Command;
use Modules\Plm\Application\Services\PlmService;

class AuditPlmCommand extends Command
{
    protected $signature = 'plm:audit';

    protected $description = 'Audit Product Lifecycle Management (PLM) ECO hash-chain and EBOM-to-MBOM integrity (0 discrepancies)';

    public function handle(PlmService $service): int
    {
        $this->info('Starting Product Lifecycle Management (PLM) Audit...');

        $res = $service->auditPlm();

        $this->line("Status: {$res['status']}");
        $this->line("Projects: {$res['project_count']}");
        $this->line("EBOMs: {$res['ebom_count']}");
        $this->line("ECOs: {$res['eco_count']}");
        $this->line("Discrepancies: {$res['discrepancies']}");

        if ($res['discrepancies'] > 0) {
            $this->error('PLM audit FAILED: Invariant violation detected!');

            return self::FAILURE;
        }

        $this->info('PLM audit PASSED with 0 discrepancy.');

        return self::SUCCESS;
    }
}
