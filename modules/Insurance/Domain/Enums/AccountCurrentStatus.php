<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

use Modules\Insurance\Domain\Exceptions\InvalidStatusTransitionException;

enum AccountCurrentStatus: string
{
    case Open = 'open';
    case Issued = 'issued';
    case Disputed = 'disputed';
    case Settled = 'settled';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Open => [self::Issued],
            self::Issued => [self::Disputed, self::Settled],
            self::Disputed => [self::Issued],
            self::Settled => [],
        };
    }

    public function assertCanTransitionTo(self $to): void
    {
        if (! in_array($to, $this->transitions(), true)) {
            throw new InvalidStatusTransitionException('account current', $this->value, $to->value);
        }
    }
}
