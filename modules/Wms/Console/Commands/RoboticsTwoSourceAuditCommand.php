<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Services\RoboticsTwoSourceAuditService;

/**
 * Command audit dua sumber deterministik untuk robotika gudang (D279-9, U-11, T-10).
 */
final class RoboticsTwoSourceAuditCommand extends Command
{
    protected $signature = 'robotics:audit';

    protected $description = 'Audit dua sumber robotika gudang (zona, reservasi, misi offline, wave, ROI)';

    public function handle(RoboticsTwoSourceAuditService $audit): int
    {
        $report = $audit->runAudit();

        if (! $report['ok']) {
            foreach ($report['issues'] as $issue) {
                $this->error($issue);
            }
            $this->error("robotics:audit FAILED (issues={$report['issue_count']}, hash={$report['state_hash']}).");

            return self::FAILURE;
        }

        $this->info("robotics:audit PASSED (issues=0, hash={$report['state_hash']}).");

        return self::SUCCESS;
    }
}
