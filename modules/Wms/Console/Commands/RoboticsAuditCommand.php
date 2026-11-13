<?php

declare(strict_types=1);

namespace Modules\Wms\Console\Commands;

use Illuminate\Console\Command;
use Modules\Wms\Application\Services\RoboticsAuditService;

/**
 * Audit khusus integritas kunci zona dan misi robotika gudang (Fase 279).
 */
class RoboticsAuditCommand extends Command
{
    protected $signature = 'wms:robotics-audit';

    protected $description = 'Audit integritas kunci zona, misi, dan rencana ROI robotika gudang';

    public function handle(RoboticsAuditService $auditService): int
    {
        $violations = $auditService->findViolations();

        if ($violations !== []) {
            foreach ($violations as $violation) {
                $this->error($violation);
            }

            return self::FAILURE;
        }

        $this->info('✓ wms:robotics-audit bersih: 0 pelanggaran.');

        return self::SUCCESS;
    }
}

