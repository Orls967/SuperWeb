<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum OutletType: string
{
    case OUTLET = 'outlet';
    case CENTRAL_KITCHEN = 'central_kitchen';

    public function label(): string
    {
        return match ($this) {
            self::OUTLET => 'Outlet Resto',
            self::CENTRAL_KITCHEN => 'Dapur Sentral (Central Kitchen)',
        };
    }
}
