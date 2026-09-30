<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum ServiceStyle: string
{
    case HIDANG = 'hidang';
    case PESAN = 'pesan';
    case MINUMAN = 'minuman';
    case PAKET = 'paket';

    public function label(): string
    {
        return match ($this) {
            self::HIDANG => 'Hidang di Meja (Bayar Bila Disentuh)',
            self::PESAN => 'Dipesan / Dimasak Saat Order',
            self::MINUMAN => 'Minuman',
            self::PAKET => 'Paket Makanan',
        };
    }
}
