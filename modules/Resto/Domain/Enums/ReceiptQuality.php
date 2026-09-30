<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum ReceiptQuality: string
{
    case GOOD = 'good';
    case PARTIAL_REJECT = 'partial_reject';

    public function label(): string
    {
        return match ($this) {
            self::GOOD => 'Kualitas Baik Sesuai Standar',
            self::PARTIAL_REJECT => 'Ditolak Sebagian (Cacat/Busuk)',
        };
    }
}
