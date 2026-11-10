<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipelines\Simulation\Steps;

use Closure;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionContext;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionPipeInterface;
use Modules\Core\Application\Services\SimTickRegistry;

final class ExecuteHandlersStep implements DayExecutionPipeInterface
{
    public function __construct(
        private readonly SimTickRegistry $tickRegistry,
    ) {}

    public function handle(DayExecutionContext $context, Closure $next): DayExecutionContext
    {
        $orderedHandlers = $this->tickRegistry->getTopologicallyOrderedHandlers();

        foreach ($orderedHandlers as $handler) {
            if ($handler->shouldRun($context->virtualClockNow, $context->dayNumber)) {
                $output = $handler->handle($context->virtualClockNow, $context->dayNumber);
                $context->recordHandlerOutput($handler->name(), $output);
            }
        }

        return $next($context);
    }
}
