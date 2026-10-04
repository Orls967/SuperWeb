<?php

declare(strict_types=1);

namespace Modules\Asset\Domain\Enums;

enum AssetEventType: string
{
    case Acquisition = 'acquisition';
    case Move = 'move';
    case Repair = 'repair';
    case Revaluation = 'revaluation';
    case Disposal = 'disposal';
    case Insurance = 'insurance';
    case Assignment = 'assignment';
    case Stocktake = 'stocktake';
    case Depreciation = 'depreciation';
    case Maintenance = 'maintenance';
    case Lease = 'lease';

    public function label(): string
    {
        return match ($this) {
            self::Acquisition => 'Akuisisi',
            self::Move => 'Mutasi Lokasi',
            self::Repair => 'Perbaikan',
            self::Revaluation => 'Revaluasi',
            self::Disposal => 'Disposal',
            self::Insurance => 'Asuransi',
            self::Assignment => 'Penugasan',
            self::Stocktake => 'Stok Opname',
            self::Depreciation => 'Penyusutan',
            self::Maintenance => 'Pemeliharaan',
            self::Lease => 'Sewa',
        };
    }
}
