<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

use Modules\Insurance\Domain\Exceptions\InvalidStatusTransitionException;

enum CatRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Certified = 'certified';
    case Failed = 'failed';
    case Superseded = 'superseded';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Queued => [self::Running, self::Failed],
            self::Running => [self::Completed, self::Failed],
            self::Completed => [self::Certified, self::Failed],
            self::Certified => [self::Superseded],
            self::Failed, self::Superseded => [],
        };
    }

    public function assertCanTransitionTo(self $to): void
    {
        if (! in_array($to, $this->transitions(), true)) {
            throw new InvalidStatusTransitionException('CAT run', $this->value, $to->value);
        }
    }
}
