<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Csp;

final readonly class ArcConsistencyResult
{
    /**
     * @param  array<string, list<mixed>>  $domains
     */
    public function __construct(
        public array $domains,
        public bool $isConsistent,
        public string $certificate,
    ) {}
}
