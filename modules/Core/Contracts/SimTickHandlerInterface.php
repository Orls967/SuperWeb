<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Carbon\CarbonInterface;

interface SimTickHandlerInterface
{
    /**
     * Unique identifier of the tick handler.
     */
    public function name(): string;

    /**
     * Whether this tick handler should execute on the given virtual time / day.
     */
    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool;

    /**
     * Execute the tick action deterministically.
     *
     * @param  (callable(string, array<string, mixed>): int)|null  $commandRunner  Optional command executor (e.g. callSilent)
     * @return array<string, mixed> Execution summary and metrics for checkpoint hash calculation
     */
    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array;
}
