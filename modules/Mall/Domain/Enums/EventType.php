<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum EventType: string
{
    case BAZAAR = 'bazaar';
    case EXHIBITION = 'exhibition';
    case CONCERT = 'concert';
    case PRODUCT_LAUNCH = 'product_launch';
    case COMMUNITY = 'community';

    public function label(): string
    {
        return match ($this) {
            self::BAZAAR => 'Bazaar & Kuliner',
            self::EXHIBITION => 'Pameran / Ekshibisi',
            self::CONCERT => 'Konser & Panggung Musik',
            self::PRODUCT_LAUNCH => 'Peluncuran Produk (Product Launch)',
            self::COMMUNITY => 'Acara Komunitas',
        };
    }
}
