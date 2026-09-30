<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum DeliveryStatus: string
{
    case PREPARING = 'preparing';
    case ON_THE_WAY = 'on_the_way';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PREPARING => 'Sedang Dipersiapkan',
            self::ON_THE_WAY => 'Dalam Pengantaran',
            self::DELIVERED => 'Terkirim',
            self::FAILED => 'Gagal Terkirim',
        };
    }
}
