<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Actions;

use DateTimeImmutable;
use Modules\Platform\Domain\Enums\ChangeStatus;
use Modules\Platform\Domain\Models\ChangeRequest;
use Modules\Platform\Domain\Models\ReleaseTrain;
use Modules\Platform\Domain\StateMachines\ChangeRequestStateMachine;

final class ScheduleChangeRequestAction
{
    public function execute(ChangeRequest $cr, ReleaseTrain $train, DateTimeImmutable $scheduledTime, int $operatorId): void
    {
        $cr->release_train_id = $train->id;
        $cr->scheduled_at = $scheduledTime;
        $cr->save();

        $sm = new ChangeRequestStateMachine($cr);
        $sm->transitionTo(ChangeStatus::SCHEDULED, $operatorId, "Dijadwalkan masuk ke gerbong rilis {$train->train_code}");
    }
}
