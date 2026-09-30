<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum TransferStatus: string
{
    case DRAFT = 'draft';
    case IN_TRANSIT = 'in_transit';
    case RECEIVED = 'received';
    case DISCREPANCY = 'discrepancy';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf Pengiriman',
            self::IN_TRANSIT => 'Dalam Perjalanan (In Transit)',
            self::RECEIVED => 'Diterima Lengkap',
            self::DISCREPANCY => 'Terdapat Selisih Penerimaan',
        };
    }
}
