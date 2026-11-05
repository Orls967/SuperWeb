<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum MicroFacilityStatus: string
{
    case DRAFT = 'draft';
    case SURVEYED = 'surveyed';
    case DPS_REVIEW = 'dps_review';
    case APPROVED = 'approved';
    case ACTIVE = 'active';
    case PAID_OFF = 'paid_off';
    case RESTRUCTURED = 'restructured';
    case DEFAULTED = 'defaulted';
    case WRITTEN_OFF = 'written_off';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::DRAFT => in_array($target, [self::SURVEYED, self::WRITTEN_OFF], true),
            self::SURVEYED => in_array($target, [self::DPS_REVIEW, self::WRITTEN_OFF], true),
            self::DPS_REVIEW => in_array($target, [self::APPROVED, self::WRITTEN_OFF], true),
            self::APPROVED => in_array($target, [self::ACTIVE, self::WRITTEN_OFF], true),
            self::ACTIVE => in_array($target, [self::PAID_OFF, self::RESTRUCTURED, self::DEFAULTED], true),
            self::RESTRUCTURED => in_array($target, [self::PAID_OFF, self::DEFAULTED], true),
            self::DEFAULTED => in_array($target, [self::PAID_OFF, self::RESTRUCTURED, self::WRITTEN_OFF], true),
            self::PAID_OFF, self::WRITTEN_OFF => false,
        };
    }
}
