<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum ItemSource: string
{
    case HIDANG = 'hidang';
    case PESAN = 'pesan';

    public function label(): string
    {
        return match ($this) {
            self::HIDANG => 'Piring Hidang (Etalase)',
            self::PESAN => 'Dipesan Langsung',
        };
    }
}
