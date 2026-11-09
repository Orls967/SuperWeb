<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\ChangeDeployment;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\ReleaseTrain;
use Modules\Platform\Domain\StateMachines\ChangeRequestStateMachine;

final class InitiateDeploymentAction
{
    public function execute(ReleaseTrain $train, string $environment, string $strategy, int $trafficPct, int $operatorId): ChangeDeployment
    {
        $deployment = ChangeDeployment::create([
            'release_train_id' => $train->id,
            'environment' => $environment,
            'strategy' => $strategy,
            'traffic_percentage' => $trafficPct,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        // Transition assigned changes to deploying
        $changes = ChangeRequest::where('release_train_id', $train->id)->where('status', 'scheduled')->get();
        foreach ($changes as $cr) {
            $sm = new ChangeRequestStateMachine($cr);
            if ($sm->canTransitionTo(ChangeStatus::DEPLOYING)) {
                $sm->transitionTo(ChangeStatus::DEPLOYING, $operatorId, "Deployment #{$deployment->id} dimulai pada lingkungan {$environment}.");
            }
        }

        return $deployment;
    }
}
