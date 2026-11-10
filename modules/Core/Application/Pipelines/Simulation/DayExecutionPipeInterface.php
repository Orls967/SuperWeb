<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipelines\Simulation;

use Closure;

/**
 * Kontrak untuk setiap langkah pipa eksekusi hari simulasi.
 */
interface DayExecutionPipeInterface
{
    /**
     * @param  Closure(DayExecutionContext): DayExecutionContext  $next
     */
    public function handle(DayExecutionContext $context, Closure $next): DayExecutionContext;
}
