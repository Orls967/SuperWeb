<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum OrderChannel: string
{
    case DINE_IN = 'dine_in';
    case TAKEAWAY = 'takeaway';
    case DELIVERY = 'delivery';
    case CATERING = 'catering';

    public function label(): string
    {
        return match ($this) {
            self::DINE_IN => 'Makan di Tempat (Dine In)',
            self::TAKEAWAY => 'Bungkus (Takeaway)',
            self::DELIVERY => 'Pesan Antar (Delivery)',
            self::CATERING => 'Katering & Prasmanan',
        };
    }
}
