<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum WaqfType: string
{
    case MONEY = 'MONEY';
    case ASSET = 'ASSET';
}
