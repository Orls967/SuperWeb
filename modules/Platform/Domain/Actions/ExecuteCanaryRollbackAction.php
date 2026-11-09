<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Models\CanaryObservation;
use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Services\CanaryRollbackOrchestrator;
use Modules\Platform\Domain\ValueObjects\RollbackExecutionResult;

final class ExecuteCanaryRollbackAction
{
    public function __construct(
        private readonly CanaryRollbackOrchestrator $orchestrator
    ) {}

    public function execute(ChangeDeployment $deployment, CanaryObservation $observation): RollbackExecutionResult
    {
        return $this->orchestrator->executeAutomatedRollback($deployment, $observation);
    }
}
