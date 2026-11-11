<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Modules\Core\Domain\Enums\ViolationCategory;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\StateMachines\BoundaryViolationStateMachine;

final class TriageViolationAction
{
    public function __construct(
        private readonly BoundaryViolationStateMachine $stateMachine = new BoundaryViolationStateMachine,
    ) {}

    public function execute(
        CoreBoundaryViolation $violation,
        string $actorId,
        string $reason,
        ?ViolationCategory $category = null,
        ?string $ownerModule = null,
    ): CoreBoundaryViolation {
        if ($category !== null) {
            $violation->category = $category;
        }
        if ($ownerModule !== null) {
            $violation->owner_module = $ownerModule;
        }

        return $this->stateMachine->transition(
            $violation,
            ViolationStatus::TRIAGED,
            $actorId,
            $reason,
        );
    }
}
