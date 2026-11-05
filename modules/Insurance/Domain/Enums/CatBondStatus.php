<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

use Modules\Insurance\Domain\Exceptions\InvalidStatusTransitionException;

enum CatBondStatus: string
{
    case Draft = 'draft';
    case Structured = 'structured';
    case Issued = 'issued';
    case Active = 'active';
    case Triggered = 'triggered';
    case WrittenDown = 'written_down';
    case Matured = 'matured';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Structured],
            self::Structured => [self::Issued],
            self::Issued => [self::Active],
            self::Active => [self::Triggered, self::Matured],
            self::Triggered => [self::WrittenDown],
            self::WrittenDown => [self::Active, self::Matured],
            self::Matured => [],
        };
    }

    public function assertCanTransitionTo(self $to): void
    {
        if (! in_array($to, $this->transitions(), true)) {
            throw new InvalidStatusTransitionException('cat bond', $this->value, $to->value);
        }
    }

    public function paysCoupons(): bool
    {
        return in_array($this, [self::Active, self::WrittenDown], true);
    }
}
