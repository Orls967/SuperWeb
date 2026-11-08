<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class AirworthinessResult
{
    /**
     * @param bool $isPermitted
     * @param list<string> $reasons
     * @param int $hoursMargin
     * @param int $cyclesMargin
     * @param AirworthinessCertificate $certificate
     */
    public function __construct(
        public readonly bool $isPermitted,
        public readonly array $reasons,
        public readonly int $hoursMargin,
        public readonly int $cyclesMargin,
        public readonly AirworthinessCertificate $certificate
    ) {
    }
}

