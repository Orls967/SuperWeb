<?php

declare(strict_types=1);

namespace Modules\Resto\Domain\Enums;

enum StockCountStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf Opname',
            self::SUBMITTED => 'Menunggu Approval Manager',
            self::APPROVED => 'Disetujui (Stok Disesuaikan)',
            self::REJECTED => 'Ditolak',
        };
    }
}
