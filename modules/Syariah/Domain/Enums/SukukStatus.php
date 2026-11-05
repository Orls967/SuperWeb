<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum SukukStatus: string
{
    case STRUCTURING = 'STRUCTURING';
    case DPS_REVIEW = 'DPS_REVIEW';
    case APPROVED = 'APPROVED';
    case ISSUED = 'ISSUED';
    case DISTRIBUTING = 'DISTRIBUTING';
    case MATURED = 'MATURED';
    case REDEEMED = 'REDEEMED';
    case DEFAULTED = 'DEFAULTED';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::STRUCTURING => [self::DPS_REVIEW],
            self::DPS_REVIEW => [self::APPROVED, self::STRUCTURING],
            self::APPROVED => [self::ISSUED],
            self::ISSUED => [self::DISTRIBUTING, self::DEFAULTED],
            self::DISTRIBUTING => [self::MATURED, self::DEFAULTED],
            self::MATURED => [self::REDEEMED, self::DEFAULTED],
            self::REDEEMED => [],
            self::DEFAULTED => [self::REDEEMED],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
