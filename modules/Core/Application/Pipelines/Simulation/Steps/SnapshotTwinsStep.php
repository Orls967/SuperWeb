<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipelines\Simulation\Steps;

use Closure;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionContext;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionPipeInterface;
use Modules\Core\Application\Services\Simulation\TwinSnapshotArchiveService;

final class SnapshotTwinsStep implements DayExecutionPipeInterface
{
    public function __construct(
        private readonly TwinSnapshotArchiveService $archiveService,
    ) {}

    public function handle(DayExecutionContext $context, Closure $next): DayExecutionContext
    {
        if ($context->isMilestoneDay && $context->run->mode !== 'sandbox') {
            $this->archiveService->snapshotAllActiveEntities($context->dayNumber, $context->run->id);
        }

        return $next($context);
    }
}
