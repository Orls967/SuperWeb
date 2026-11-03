<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Csp;

final readonly class QuickXplainResult
{
    /**
     * @param  array<string, mixed>  $minimalConflict
     */
    public function __construct(
        public array $minimalConflict,
        public string $certificate,
    ) {}
}
