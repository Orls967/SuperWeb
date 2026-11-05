<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum BmtSavingsContractType: string
{
    case WADIAH_YAD_DHAMANAH = 'WADIAH_YAD_DHAMANAH';
    case MUDHARABAH_MUTLAQAH = 'MUDHARABAH_MUTLAQAH';
    case MUDHARABAH_MUQAYYADAH = 'MUDHARABAH_MUQAYYADAH';

    public function label(): string
    {
        return match ($this) {
            self::WADIAH_YAD_DHAMANAH => 'Wadiah Yad Dhamanah (Titipan Bergaransi)',
            self::MUDHARABAH_MUTLAQAH => 'Mudharabah Mutlaqah (Investasi Fleksibel)',
            self::MUDHARABAH_MUQAYYADAH => 'Mudharabah Muqayyadah (Investasi Terikat)',
        };
    }
}
