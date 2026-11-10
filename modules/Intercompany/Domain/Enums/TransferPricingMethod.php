<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Enums;

enum TransferPricingMethod: string
{
    case CUP = 'CUP';
    case CPM = 'CPM';
    case RPM = 'RPM';
    case TNMM = 'TNMM';

    public function description(): string
    {
        return match ($this) {
            self::CUP => 'Comparable Uncontrolled Price Method (Harga Pasar Sebanding)',
            self::CPM => 'Cost Plus Method (Biaya Perolehan Ditambah Markup)',
            self::RPM => 'Resale Price Method (Harga Jual Kembali Dikurangi Margin)',
            self::TNMM => 'Transactional Net Margin Method (Margin Laba Bersih Transaksional)',
        };
    }
}
