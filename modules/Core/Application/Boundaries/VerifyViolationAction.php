<?php

declare(strict_types=1);

namespace Modules\Core\Application\Boundaries;

use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;
use Modules\Core\Domain\StateMachines\BoundaryViolationStateMachine;

final class VerifyViolationAction
{
    public function __construct(
        private readonly BoundaryViolationStateMachine $stateMachine = new BoundaryViolationStateMachine,
    ) {}

    public function execute(
        CoreBoundaryViolation $violation,
        string $actorId,
        string $reason = 'Violation verified removed from code and tests pass',
    ): CoreBoundaryViolation {
        return $this->stateMachine->transition(
            $violation,
            ViolationStatus::VERIFIED,
            $actorId,
            $reason,
        );
    }
}

