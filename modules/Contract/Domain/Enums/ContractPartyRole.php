<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Enums;

enum ContractPartyRole: string
{
    case FirstParty = 'first_party'; // Pihak Pertama (Pengguna Jasa / Pembeli / Pemberi Sewa)
    case SecondParty = 'second_party'; // Pihak Kedua (Penyedia Jasa / Penjual / Penyewa)
    case Guarantor = 'guarantor'; // Penjamin (Corporate Guarantee / Surety)
    case Witness = 'witness'; // Saksi Legal / Notaris
    case ThirdParty = 'third_party'; // Pihak Ketiga (Mitra Tambahan / Konsorsium)

    public function label(): string
    {
        return match ($this) {
            self::FirstParty => 'Pihak Pertama',
            self::SecondParty => 'Pihak Kedua',
            self::Guarantor => 'Penjamin (Guarantor)',
            self::Witness => 'Saksi / Notaris',
            self::ThirdParty => 'Pihak Ketiga / Konsorsium',
        };
    }
}
