<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum TenantCategory: string
{
    case FNB = 'fnb';
    case FASHION = 'fashion';
    case AUTOMOTIVE = 'automotive';
    case ENTERTAINMENT = 'entertainment';
    case ELECTRONICS = 'electronics';
    case SERVICES = 'services';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FNB => 'Makanan & Minuman (F&B)',
            self::FASHION => 'Fashion & Busana',
            self::AUTOMOTIVE => 'Otomotif & Bengkel',
            self::ENTERTAINMENT => 'Hiburan & Bioskop',
            self::ELECTRONICS => 'Gadget & Elektronik',
            self::SERVICES => 'Layanan & Perbankan',
            self::OTHER => 'Lainnya',
        };
    }
}
