<?php

declare(strict_types=1);

namespace Modules\Insurance\Domain\Enums;

use Modules\Insurance\Domain\Exceptions\InvalidStatusTransitionException;

enum SolvencyActionStatus: string
{
    case Proposed = 'proposed';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Executed = 'executed';
    case Rejected = 'rejected';

    /** @return list<self> */
    public function transitions(): array
    {
        return match ($this) {
            self::Proposed => [self::PendingApproval, self::Rejected],
            self::PendingApproval => [self::Approved, self::Rejected],
            self::Approved => [self::Executed],
            self::Executed, self::Rejected => [],
        };
    }

    public function assertCanTransitionTo(self $to): void
    {
        if (! in_array($to, $this->transitions(), true)) {
            throw new InvalidStatusTransitionException('aksi solvabilitas', $this->value, $to->value);
        }
    }
}
