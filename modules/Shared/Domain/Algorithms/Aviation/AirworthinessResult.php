<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class AirworthinessResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly bool $isPermitted,
        public readonly array $reasons,
        public readonly int $hoursMargin,
        public readonly int $cyclesMargin,
        public readonly AirworthinessCertificate $certificate
    ) {}
}
