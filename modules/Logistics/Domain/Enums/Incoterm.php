<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum Incoterm: string
{
    case EXW = 'EXW';
    case FOB = 'FOB';
    case CIF = 'CIF';
    case DAP = 'DAP';
    case DDP = 'DDP';

    public function description(): string
    {
        return match ($this) {
            self::EXW => 'Ex Works (Pabrik)',
            self::FOB => 'Free on Board (Pelabuhan Asal)',
            self::CIF => 'Cost, Insurance & Freight (Pelabuhan Tujuan)',
            self::DAP => 'Delivered at Place (Tempat Pembeli)',
            self::DDP => 'Delivered Duty Paid (Pajak Ditanggung Penjual)',
        };
    }
}
