<?php

declare(strict_types=1);

namespace Modules\Core\Application\Pipelines\Simulation;

use Illuminate\Pipeline\Pipeline;
use Modules\Core\Application\Pipelines\Simulation\Steps\ExecuteHandlersStep;
use Modules\Core\Application\Pipelines\Simulation\Steps\PersistCheckpointStep;
use Modules\Core\Application\Pipelines\Simulation\Steps\PrepareDayStep;
use Modules\Core\Application\Pipelines\Simulation\Steps\SnapshotTwinsStep;
use Modules\Core\Application\Pipelines\Simulation\Steps\UpdateHashChainStep;
use Modules\Core\Application\Pipelines\Simulation\Steps\VerifyLedgerStep;

/**
 * Pipa eksekusi modular satu hari virtual simulasi.
 */
final class DayExecutionPipeline
{
    /** @var list<class-string<DayExecutionPipeInterface>> */
    private array $pipes = [
        PrepareDayStep::class,
        ExecuteHandlersStep::class,
        VerifyLedgerStep::class,
        UpdateHashChainStep::class,
        PersistCheckpointStep::class,
        SnapshotTwinsStep::class,
    ];

    public function __construct(
        private readonly Pipeline $pipeline,
    ) {}

    public function process(DayExecutionContext $context): DayExecutionContext
    {
        $start = microtime(true);

        /** @var DayExecutionContext $result */
        $result = $this->pipeline
            ->send($context)
            ->through($this->pipes)
            ->then(fn (DayExecutionContext $ctx) => $ctx);

        $result->dayExecutionTimeMs = round((microtime(true) - $start) * 1000, 2);

        return $result;
    }
}
