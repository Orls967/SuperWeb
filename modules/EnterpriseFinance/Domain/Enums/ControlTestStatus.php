<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Enums;

enum ControlTestStatus: string
{
    case EFFECTIVE = 'EFFECTIVE';
    case DEFICIENT = 'DEFICIENT';
    case FAILED = 'FAILED';
}

