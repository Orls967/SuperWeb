<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum IslamicTradeContractType: string
{
    case ISTISNA = 'istisna';
    case WAKALAH_BI_AL_UJRAH = 'wakalah_bi_al_ujrah';
    case SALAM = 'salam';
    case PARALLEL_SALAM = 'parallel_salam';
    case SCF_MURABAHAH = 'scf_murabahah';
    case TAWARRUQ_MUNAZZAM = 'tawarruq_munazzam';
}
