<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math\Exceptions;

use RuntimeException;

final class CycleDetectedException extends RuntimeException
{
    /**
     * @param  list<string>  $cyclePath
     */
    public function __construct(
        public readonly array $cyclePath,
        string $message = 'Cycle detected in directed acyclic graph'
    ) {
        $pathString = implode(' -> ', $cyclePath);
        parent::__construct("{$message}: [{$pathString}]");
    }
}
