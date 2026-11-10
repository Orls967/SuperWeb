<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipelines\Simulation\Steps;

use Closure;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionContext;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionPipeInterface;

final class UpdateHashChainStep implements DayExecutionPipeInterface
{
    public function handle(DayExecutionContext $context, Closure $next): DayExecutionContext
    {
        $daySummary = $context->buildDaySummary();
        ksort($daySummary);
        $canonicalJson = json_encode($daySummary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $payload = "{$context->previousChainHash}|{$context->dayNumber}|{$canonicalJson}";
        $context->currentChainHash = hash('sha256', $payload);

        return $next($context);
    }
}
