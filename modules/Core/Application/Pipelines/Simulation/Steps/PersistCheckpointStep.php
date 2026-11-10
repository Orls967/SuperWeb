<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipelines\Simulation\Steps;

use Closure;
use Modules\Core\Application\Actions\Simulation\CheckpointSimulationAction;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionContext;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionPipeInterface;

final class PersistCheckpointStep implements DayExecutionPipeInterface
{
    public function __construct(
        private readonly CheckpointSimulationAction $checkpointAction,
    ) {}

    public function handle(DayExecutionContext $context, Closure $next): DayExecutionContext
    {
        if ($context->isMilestoneDay && $context->run->mode !== 'sandbox') {
            $this->checkpointAction->execute(
                run: $context->run,
                dayNumber: $context->dayNumber,
                chainHash: $context->currentChainHash,
                handlerHashes: $context->handlerHashes,
                stateSummary: $context->buildDaySummary(),
            );
        }

        return $next($context);
    }
}
