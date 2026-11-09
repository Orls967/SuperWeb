<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Enums;

enum DicomModality: string
{
    case CT = 'CT';
    case MRI = 'MRI';
    case XR = 'XR';
    case US = 'US';
    case NM = 'NM';
    case PET = 'PET';
    case MAMMO = 'MAMMO';
}
