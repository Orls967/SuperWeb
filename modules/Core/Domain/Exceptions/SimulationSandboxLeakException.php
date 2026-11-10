<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use RuntimeException;

final class SimulationSandboxLeakException extends RuntimeException
{
    /**
     * @param  array<string, int>  $leakedTables
     */
    public function __construct(
        string $message,
        public readonly array $leakedTables = [],
    ) {
        parent::__construct($message);
    }
}
