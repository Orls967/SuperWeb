<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Enums;

enum TradeStatus: string
{
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
