<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Events;

use Modules\Platform\Domain\Models\SandboxEnvironment;

final class SandboxCreatedEvent
{
    public function __construct(
        public readonly SandboxEnvironment $sandbox,
        public readonly string $occurredAt
    ) {
    }

    public static function fromSandbox(SandboxEnvironment $sandbox): self
    {
        return new self($sandbox, date('c'));
    }
}

