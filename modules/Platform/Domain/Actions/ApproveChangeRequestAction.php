<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\StateMachines\ChangeRequestStateMachine;

final class ApproveChangeRequestAction
{
    public function execute(ChangeRequest $cr, int $approverId, string $approvalReason): void
    {
        $sm = new ChangeRequestStateMachine($cr);
        $sm->transitionTo(ChangeStatus::APPROVED, $approverId, $approvalReason);
    }
}
