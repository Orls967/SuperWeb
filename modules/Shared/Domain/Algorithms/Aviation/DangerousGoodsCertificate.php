<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class DangerousGoodsCertificate
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        public readonly string $unNumber,
        public readonly string $mode,
        public readonly int $weightGrams,
        public readonly ?int $sectorDurationMinutes,
        public readonly ?int $uldHoldoverMinutes,
        public readonly bool $isEligible,
        public readonly array $violations
    ) {}

    public function hash(): string
    {
        return hash('sha256', sprintf(
            '%s|%s|%d|%s|%s|%d|%s',
            $this->unNumber,
            $this->mode,
            $this->weightGrams,
            $this->sectorDurationMinutes ?? 'null',
            $this->uldHoldoverMinutes ?? 'null',
            $this->isEligible ? 1 : 0,
            implode(',', $this->violations)
        ));
    }
}
