<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use RuntimeException;

class SchemaBreakingChangeException extends RuntimeException
{
    /**
     * @param  array<int, array{path: string, code: string}>  $violations
     */
    public function __construct(
        string $message,
        protected array $violations = []
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<int, array{path: string, code: string}>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
