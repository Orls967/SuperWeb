<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipelines\Simulation\Steps;

use Closure;
use Modules\Core\Application\Actions\Simulation\ProbeLedgerBalanceAction;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionContext;
use Modules\Core\Application\Pipelines\Simulation\DayExecutionPipeInterface;

final class VerifyLedgerStep implements DayExecutionPipeInterface
{
    public function __construct(
        private readonly ProbeLedgerBalanceAction $ledgerProbe,
    ) {}

    public function handle(DayExecutionContext $context, Closure $next): DayExecutionContext
    {
        // Hanya verifikasi jika mode sandbox atau hari checkpoint milestone
        if ($context->run->mode === 'sandbox' || $context->isMilestoneDay) {
            $this->ledgerProbe->execute();
        }

        return $next($context);
    }
}
