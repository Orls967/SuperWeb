<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts;

use Modules\Core\Application\DTOs\Resilience\DrillExecutionDto;
use Modules\Core\Domain\Models\CoreDrDrillExecution;

interface DisasterRecoveryDrillInterface
{
    /**
     * Execute a disaster recovery chaos/failover drill deterministically.
     */
    public function executeDrill(DrillExecutionDto $dto): CoreDrDrillExecution;

    /**
     * Compute data checksum across mock/production region replicas to verify zero data loss.
     */
    public function computeDataIntegrityChecksum(string $region): string;
}
