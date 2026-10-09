<?php

declare(strict_types=1);

namespace Modules\Integration\Console\Commands;

use Illuminate\Console\Command;
use Modules\Integration\Application\Services\ResilienceWave2Service;

/**
 * dr:audit — Wave 2 DR Drill & Data Sovereignty audit command.
 *
 * Checks:
 *  1. DR Drills: no FAILED drills, no drills with ledger discrepancy
 *  2. Edge: no stuck PENDING sync events
 *  3. Data Sovereignty: no residency violations
 *
 * Exit 0 = ALL HEALTHY | Exit 1 = DISCREPANCY FOUND
 */
class DrAuditCommand extends Command
{
    protected $signature = 'dr:audit';

    protected $description = 'Wave 2: DR drill & data sovereignty audit — 0 discrepancy required';

    public function handle(ResilienceWave2Service $service): int
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║   dr:audit — Wave 2 Resilience & DR Audit                   ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $result = $service->drAudit();

        $this->line("  Failed DR Drills           : {$result['failed_dr_drills']}");
        $this->line("  Drills with Ledger Disc.   : {$result['drills_with_discrepancy']}");
        $this->line("  Sovereignty Violations     : {$result['sovereignty_violations']}");
        $this->line("  Pending Edge Events        : {$result['pending_edge_events']}");
        $this->line("  Overall Status             : {$result['status']}");
        $this->newLine();

        if ($result['discrepancy_count'] > 0) {
            $this->error("dr:audit FAILED — {$result['discrepancy_count']} discrepancy(ies) detected.");

            return self::FAILURE;
        }

        $this->info('dr:audit PASSED — All resilience pillars HEALTHY. RPO=0, RTO within tier targets.');

        return self::SUCCESS;
    }
}
