<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Algorithms;

final class MigrationSafetyViolation
{
    public function __construct(
        public readonly string $rule,
        public readonly string $file,
        public readonly int $line,
        public readonly string $message,
        public readonly string $severity = 'CRITICAL'
    ) {}

    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'file' => $this->file,
            'line' => $this->line,
            'message' => $this->message,
            'severity' => $this->severity,
        ];
    }
}
