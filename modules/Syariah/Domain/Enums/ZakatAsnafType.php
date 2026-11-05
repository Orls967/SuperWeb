<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum ZakatAsnafType: string
{
    case FAKIR = 'fakir';
    case MISKIN = 'miskin';
    case AMIL = 'amil';
    case MUALAF = 'mualaf';
    case RIQAB = 'riqab';
    case GHARIMIN = 'gharimin';
    case FISABILILLAH = 'fisabilillah';
    case IBNU_SABIL = 'ibnu_sabil';
}
