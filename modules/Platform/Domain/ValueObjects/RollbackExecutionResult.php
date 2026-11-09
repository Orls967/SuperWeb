<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\ValueObjects;

final class RollbackExecutionResult
{
    public function __construct(
        private readonly bool $success,
        private readonly string $message
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
