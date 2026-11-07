<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use Modules\Mining\Domain\Enums\FurnaceRunStatus;
use Modules\Mining\Domain\Models\MiningSmelterFurnaceRun;
use Modules\Mining\Domain\StateMachines\FurnaceRunStateMachine;

final class AbortSmelterFurnaceRunAction
{
    public function __construct(
        private readonly FurnaceRunStateMachine $stateMachine
    ) {}

    public function execute(
        MiningSmelterFurnaceRun $run,
        string $abortReason = 'Emergency Trip Tanur / Masalah Refraktori'
    ): MiningSmelterFurnaceRun {
        $run->metadata = array_merge($run->metadata ?? [], [
            'abort_reason' => $abortReason,
            'aborted_at' => now()->toIso8601String(),
        ]);
        $run->save();

        $this->stateMachine->transition($run, FurnaceRunStatus::ABORTED);

        return $run;
    }
}
