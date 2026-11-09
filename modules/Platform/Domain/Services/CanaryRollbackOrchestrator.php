<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Services;

use Modules\Platform\Domain\Models\CanaryObservation;
use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\ValueObjects\RollbackExecutionResult;

class CanaryRollbackOrchestrator
{
    public function executeAutomatedRollback(ChangeDeployment $deployment, CanaryObservation $observation): RollbackExecutionResult
    {
        // 1. Mark deployment as rolled_back
        $deployment->status = 'rolled_back';
        $deployment->completed_at = now();
        $deployment->save();

        // 2. Mark related Change Requests under the Release Train as rolled_back
        $train = $deployment->releaseTrain;
        if ($train !== null) {
            ChangeRequest::where('release_train_id', $train->id)
                ->where('status', 'deploying')
                ->update([
                    'status' => 'rolled_back',
                ]);
        }

        return new RollbackExecutionResult(
            true,
            "Rollback otomatis berhasil dieksekusi untuk deployment #{$deployment->id}. Status deployment dan change request dipulihkan."
        );
    }
}
