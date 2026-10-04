<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Negotiation = 'negotiation';
    case Approved = 'approved';
    case Signed = 'signed';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Renewed = 'renewed';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Review, self::Terminated]),
            self::Review => in_array($target, [self::Draft, self::Negotiation, self::Approved, self::Terminated]),
            self::Negotiation => in_array($target, [self::Review, self::Approved, self::Terminated]),
            self::Approved => in_array($target, [self::Signed, self::Negotiation, self::Terminated]),
            self::Signed => in_array($target, [self::Active, self::Terminated]),
            self::Active => in_array($target, [self::Suspended, self::Expired, self::Terminated, self::Renewed]),
            self::Suspended => in_array($target, [self::Active, self::Terminated]),
            self::Expired => in_array($target, [self::Renewed]),
            self::Terminated => false,
            self::Renewed => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Review => 'Dalam Peninjauan',
            self::Negotiation => 'Negosiasi',
            self::Approved => 'Disetujui',
            self::Signed => 'Ditandatangani',
            self::Active => 'Aktif Berjalan',
            self::Suspended => 'Ditangguhkan',
            self::Expired => 'Kedaluwarsa',
            self::Terminated => 'Diakhiri (Terminated)',
            self::Renewed => 'Diperpanjang (Renewed)',
        };
    }
}
