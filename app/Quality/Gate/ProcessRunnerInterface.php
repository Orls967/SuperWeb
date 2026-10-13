<?php

declare(strict_types=1);

namespace App\Quality\Gate;

use Closure;

/**
 * Interface for executing shell process in quality gate pipeline.
 */
interface ProcessRunnerInterface
{
    /**
     * @param  array<string, string>  $env
     * @param  (Closure(string, string): void)|null  $logger
     * @return array{exit_code: int, output: string}
     */
    public function run(string $command, string $cwd, array $env = [], ?Closure $logger = null): array;
}
