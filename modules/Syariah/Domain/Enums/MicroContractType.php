<?php

declare(strict_types=1);

namespace Modules\Syariah\Domain\Enums;

enum MicroContractType: string
{
    case MUDHARABAH_BERTINGKAT = 'mudharabah_bertingkat';
    case MUSYARAKAH = 'musyarakah';
    case MURABAHAH_MIKRO = 'murabahah_mikro';
    case QARDH_AL_HASAN = 'qardh_al_hasan';
    case SALAM = 'salam';
    case MUZARAAH = 'muzaraah';

    public function label(): string
    {
        return match ($this) {
            self::MUDHARABAH_BERTINGKAT => 'Mudharabah Bertingkat',
            self::MUSYARAKAH => 'Musyarakah Kemitraan',
            self::MURABAHAH_MIKRO => 'Murabahah Modal Kerja Mikro',
            self::QARDH_AL_HASAN => 'Qardh al-Hasan Kebajikan',
            self::SALAM => 'Salam Pertanian',
            self::MUZARAAH => 'Muzaraah Bagi Hasil Tani',
        };
    }
}
