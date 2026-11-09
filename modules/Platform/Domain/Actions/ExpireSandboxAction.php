<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Enums\SandboxStatus;
use Modules\Platform\Domain\Models\SandboxEnvironment;
use Modules\Platform\Domain\StateMachines\SandboxEnvironmentStateMachine;

class ExpireSandboxAction
{
    public function expireOverdue(): int
    {
        $overdue = SandboxEnvironment::whereIn('status', [
            SandboxStatus::READY->value,
            SandboxStatus::EXPIRING->value,
        ])
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($overdue as $sandbox) {
            $sm = new SandboxEnvironmentStateMachine($sandbox);
            $sm->transitionTo(SandboxStatus::EXPIRED, 'Sandbox TTL exceeded auto-expiration threshold');
            $count++;
        }

        return $count;
    }
}
