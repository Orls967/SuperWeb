<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Enums;

enum SodSeverity: string
{
    case CRITICAL = 'CRITICAL';
    case HIGH = 'HIGH';
    case MEDIUM = 'MEDIUM';
    case LOW = 'LOW';
}

