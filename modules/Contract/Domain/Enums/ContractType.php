<?php

declare(strict_types=1);

namespace Modules\Contract\Domain\Enums;

enum ContractType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Distribution = 'distribution';
    case Agency = 'agency';
    case Lease = 'lease';
    case Service = 'service';
    case License = 'license';
    case JointVenture = 'joint_venture';
    case OemOdm = 'oem_odm';
    case Nda = 'nda';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Pembelian (Procurement / PO)',
            self::Sale => 'Penjualan (Sales Contract)',
            self::Distribution => 'Distribusi Eksklusif / Regional',
            self::Agency => 'Keagenan Komisi',
            self::Lease => 'Sewa Menyewa Properti / Aset',
            self::Service => 'Perjanjian Jasa & SLA',
            self::License => 'Lisensi Merek / Hak Cipta',
            self::JointVenture => 'Kerja Sama Usaha (JV)',
            self::OemOdm => 'Manufaktur OEM / ODM',
            self::Nda => 'Perjanjian Kerahasiaan (NDA)',
        };
    }
}
