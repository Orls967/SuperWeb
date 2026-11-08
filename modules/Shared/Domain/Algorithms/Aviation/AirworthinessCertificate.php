<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Aviation;

final class AirworthinessCertificate
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly string $evalDate,
        public readonly bool $isPermitted,
        public readonly array $reasons,
        public readonly int $hoursMargin,
        public readonly int $cyclesMargin
    ) {}

    public function hash(): string
    {
        return hash('sha256', sprintf(
            '%s|%d|%s|%d|%d',
            $this->evalDate,
            $this->isPermitted ? 1 : 0,
            implode(',', $this->reasons),
            $this->hoursMargin,
            $this->cyclesMargin
        ));
    }
}
