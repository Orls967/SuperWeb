<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class YieldOptimizerResult
{
    public function __construct(
        public readonly int $capacity,
        public readonly array $buckets,
        public readonly YieldOptimizerCertificate $certificate
    ) {}
}

