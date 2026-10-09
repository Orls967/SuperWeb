<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Enums;

enum DisposalMethod: string
{
    case Sale = 'sale';
    case WriteOff = 'write_off';
    case Donation = 'donation';
    case Loss = 'loss';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Dijual',
            self::WriteOff => 'Dihapus Buku',
            self::Donation => 'Dihibahkan',
            self::Loss => 'Hilang',
        };
    }
}
