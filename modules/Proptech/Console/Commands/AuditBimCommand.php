<?php

declare(strict_types=1);

namespace Modules\Proptech\Console\Commands;

use Illuminate\Console\Command;
use Modules\Proptech\Application\Services\BimTwinService;

class AuditBimCommand extends Command
{
    protected $signature = 'prp:audit-bim';

    protected $description = 'Perform two-source verification of BIM model version hash chains and twin component completion';

    public function handle(BimTwinService $service): int
    {
        $this->info('Starting Two-Source BIM & Digital Twin Audit...');

        $result = $service->auditBim();

        $this->line("BIM Models Checked: {$result['models_checked']}");
        $this->line("Twin Components Checked: {$result['components_checked']}");

        if (! $result['passed']) {
            $this->error('FAILED: Discrepancies detected in BIM Models or Twin Components:');
            foreach ($result['discrepancies'] as $discrepancy) {
                $this->error(" - {$discrepancy}");
            }

            return 1;
        }

        $this->info('PASS: All BIM model version hash chains and twin component bounds are intact.');

        return 0;
    }
}
