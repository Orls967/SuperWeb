<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Enums;

enum EncumbranceStatus: string
{
    case RESERVED = 'RESERVED';
    case LIQUIDATED = 'LIQUIDATED';
    case RELEASED = 'RELEASED';
}

