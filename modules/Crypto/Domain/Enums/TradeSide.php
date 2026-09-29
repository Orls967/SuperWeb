<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Enums;

enum TradeSide: string
{
    case BUY = 'buy';
    case SELL = 'sell';

    public function label(): string
    {
        return match ($this) {
            self::BUY => 'Beli',
            self::SELL => 'Jual',
        };
    }
}
