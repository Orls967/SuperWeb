<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

use Modules\Insurance\Domain\Exceptions\InvalidStatusTransitionException;

enum TreatyStatus: string
{
    case Draft = 'draft';
    case Negotiating = 'negotiating';
    case Bound = 'bound';
    case Active = 'active';
    case RunOff = 'run_off';
    case Commuted = 'commuted';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Negotiating, self::Cancelled],
            self::Negotiating => [self::Draft, self::Bound, self::Cancelled],
            self::Bound => [self::Active, self::Cancelled],
            self::Active => [self::RunOff],
            self::RunOff => [self::Commuted],
            self::Commuted, self::Cancelled => [],
        };
    }

    public function assertCanTransitionTo(self $to): void
    {
        if (! in_array($to, $this->transitions(), true)) {
            throw new InvalidStatusTransitionException('treaty', $this->value, $to->value);
        }
    }

    /** Transisi yang wajib persetujuan four-eyes (D157-1). */
    public function requiresApprovalTo(self $to): bool
    {
        return in_array($to, [self::Bound, self::Commuted], true);
    }

    /** Treaty yang masih menerima cession baru. */
    public function acceptsCessions(): bool
    {
        return $this === self::Active;
    }

    public function isTerminal(): bool
    {
        return $this->transitions() === [];
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Negotiating => 'Negosiasi',
            self::Bound => 'Terikat (bound)',
            self::Active => 'Aktif',
            self::RunOff => 'Run-off',
            self::Commuted => 'Dikomutasi',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
