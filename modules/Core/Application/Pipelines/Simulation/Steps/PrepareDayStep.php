<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipelines\Simulation\Steps;

use Closure;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionContext;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionPipeInterface;

final class PrepareDayStep implements DayExecutionPipeInterface
{
    public function handle(DayExecutionContext $context, Closure $next): DayExecutionContext
    {
        // Tentukan apakah hari ini merupakan milestone checkpoint (kelipatan 7 hari atau hari terakhir)
        $isMilestone = ($context->dayNumber % 7 === 0) || ($context->dayNumber === $context->run->total_days);
        $context->isMilestoneDay = $isMilestone;

        return $next($context);
    }
}
