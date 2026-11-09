<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use Modules\Platform\Domain\Enums\ReleaseTrainStatus;
use Modules\Platform\Domain\Models\ReleaseTrain;
use Modules\Platform\Domain\StateMachines\ReleaseTrainStateMachine;

final class FreezeReleaseTrainAction
{
    public function execute(ReleaseTrain $train): void
    {
        $sm = new ReleaseTrainStateMachine($train);
        $sm->transitionTo(ReleaseTrainStatus::FROZEN, 'Batas waktu code freeze tercapai.');
    }
}
