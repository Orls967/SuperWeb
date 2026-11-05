<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

use Modules\Insurance\Domain\Exceptions\InvalidStatusTransitionException;

enum BordereauStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Reversed = 'reversed';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent],
            self::Sent => [self::Acknowledged, self::Reversed],
            self::Acknowledged => [self::Reversed],
            self::Reversed => [],
        };
    }

    public function assertCanTransitionTo(self $to): void
    {
        if (! in_array($to, $this->transitions(), true)) {
            throw new InvalidStatusTransitionException('bordereau', $this->value, $to->value);
        }
    }

    public function isImmutable(): bool
    {
        return $this !== self::Draft;
    }
}
