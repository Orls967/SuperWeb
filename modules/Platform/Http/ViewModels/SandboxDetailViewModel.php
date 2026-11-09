<?php

declare(strict_types=1);

namespace Modules\Platform\Http\ViewModels;

use Modules\Platform\Domain\Models\SandboxEnvironment;

final class SandboxDetailViewModel
{
    public function __construct(
        public readonly SandboxEnvironment $sandbox
    ) {
    }

    public function isExtendable(): bool
    {
        return in_array($this->sandbox->status, ['ready', 'expiring'], true);
    }

    public function formattedExpiry(): string
    {
        return $this->sandbox->expires_at?->diffForHumans() ?? 'Permanent';
    }

    public function transitionCount(): int
    {
        return $this->sandbox->transitions->count();
    }
}

