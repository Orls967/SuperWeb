<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\StateMachines\ChangeRequestStateMachine;

final class CloseChangeRequestAction
{
    public function execute(ChangeRequest $cr, int $operatorId, string $resolutionNotes): void
    {
        $sm = new ChangeRequestStateMachine($cr);
        $sm->transitionTo(ChangeStatus::CLOSED, $operatorId, $resolutionNotes);
    }
}
