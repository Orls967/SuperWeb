<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\StateMachines\SandboxEnvironmentStateMachine;

class ProvisionSandboxAction
{
    public function execute(SandboxEnvironment $sandbox): SandboxEnvironment
    {
        $sm = new SandboxEnvironmentStateMachine($sandbox);

        if ($sandbox->status === SandboxStatus::REQUESTED->value) {
            $sm->transitionTo(SandboxStatus::PROVISIONING, 'Allocating sandbox database schema');
        }

        if ($sandbox->status === SandboxStatus::PROVISIONING->value) {
            $sm->transitionTo(SandboxStatus::SEEDED, 'Applying database seeds and synthetic data');
        }

        if ($sandbox->status === SandboxStatus::SEEDED->value) {
            $sm->transitionTo(SandboxStatus::READY, 'Sandbox online and ready for developers');
        }

        return $sandbox->fresh();
    }
}

