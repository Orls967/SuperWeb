<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum ConsumedState: string
{
    case PRESENTED = 'presented';
    case CONSUMED = 'consumed';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::PRESENTED => 'Dihidang di Meja',
            self::CONSUMED => 'Disentuh / Dimakan',
            self::RETURNED => 'Utuh (Kembali ke Etalase)',
        };
    }
}
