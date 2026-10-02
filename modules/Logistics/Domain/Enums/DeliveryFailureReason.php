<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Enums;

enum DeliveryFailureReason: string
{
    case ConsigneeUnavailable = 'consignee_unavailable';
    case AddressNotFound = 'address_not_found';
    case Refused = 'refused';
    case CodNotReady = 'cod_not_ready';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ConsigneeUnavailable => 'Penerima tidak ada di tempat',
            self::AddressNotFound => 'Alamat tidak ditemukan',
            self::Refused => 'Penerima menolak paket',
            self::CodNotReady => 'Dana COD tidak siap',
            self::Other => 'Alasan lain',
        };
    }
}
