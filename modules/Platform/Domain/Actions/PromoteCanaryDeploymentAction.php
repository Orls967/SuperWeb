<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\StateMachines\ChangeRequestStateMachine;

final class PromoteCanaryDeploymentAction
{
    public function execute(ChangeDeployment $deployment, int $operatorId): void
    {
        $deployment->traffic_percentage = 100;
        $deployment->status = 'succeeded';
        $deployment->completed_at = now();
        $deployment->save();

        $train = $deployment->releaseTrain;
        if ($train !== null) {
            $changes = ChangeRequest::where('release_train_id', $train->id)->where('status', 'deploying')->get();
            foreach ($changes as $cr) {
                $sm = new ChangeRequestStateMachine($cr);
                if ($sm->canTransitionTo(ChangeStatus::VERIFIED)) {
                    $sm->transitionTo(ChangeStatus::VERIFIED, $operatorId, 'Promosi ke 100% lalu lintas produksi sukses diverifikasi.');
                }
            }
        }
    }
}
