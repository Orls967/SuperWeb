<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Boundaries;

/**
 * Result of a table ownership resolution check.
 */
final class TableResolutionResult
{
    public function __construct(
        public readonly ?string $owner,
        public readonly bool $isAllowed,
        public readonly ?string $matchedPrefix,
        public readonly bool $isLegacy = false,
        public readonly ?string $reason = null,
    ) {}
}
