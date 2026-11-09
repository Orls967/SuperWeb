<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\StateMachines\SandboxEnvironmentStateMachine;

class DestroySandboxAction
{
    public function execute(SandboxEnvironment $sandbox): SandboxEnvironment
    {
        $sm = new SandboxEnvironmentStateMachine($sandbox);
        if ($sm->canTransitionTo(SandboxStatus::DESTROYED)) {
            $sm->transitionTo(SandboxStatus::DESTROYED, 'Sandbox destroyed by user or cleanup schedule');
        }

        return $sandbox->fresh();
    }
}

