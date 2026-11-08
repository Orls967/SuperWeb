<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Enums;

enum SlotRequestStatus: string
{
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case ALLOCATED = 'allocated';
    case WAITLISTED = 'waitlisted';
    case CONFIRMED = 'confirmed';
    case RETURNED = 'returned';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Diajukan',
            self::UNDER_REVIEW => 'Sedang Direview',
            self::ALLOCATED => 'Dialokasikan',
            self::WAITLISTED => 'Daftar Tunggu',
            self::CONFIRMED => 'Dikonfirmasi',
            self::RETURNED => 'Dikembalikan',
            self::REVOKED => 'Dicabut',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }
}

