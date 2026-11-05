<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum IjarahStatus: string
{
    case DRAFT = 'DRAFT';
    case DPS_REVIEW = 'DPS_REVIEW';
    case APPROVED = 'APPROVED';
    case ACTIVE = 'ACTIVE';
    case OPTION_EXERCISED = 'OPTION_EXERCISED';
    case OWNERSHIP_TRANSFERRED = 'OWNERSHIP_TRANSFERRED';
    case DEFAULTED = 'DEFAULTED';
    case TERMINATED = 'TERMINATED';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::DPS_REVIEW, self::TERMINATED],
            self::DPS_REVIEW => [self::APPROVED, self::DRAFT, self::TERMINATED],
            self::APPROVED => [self::ACTIVE, self::TERMINATED],
            self::ACTIVE => [self::OPTION_EXERCISED, self::DEFAULTED, self::TERMINATED],
            self::OPTION_EXERCISED => [self::OWNERSHIP_TRANSFERRED, self::TERMINATED],
            self::OWNERSHIP_TRANSFERRED => [],
            self::DEFAULTED => [self::ACTIVE, self::TERMINATED],
            self::TERMINATED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
