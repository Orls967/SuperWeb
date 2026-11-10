<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Enums;

enum EliminationType: string
{
    case RECIPROCAL_AR_AP = 'RECIPROCAL_AR_AP';
    case REVENUE_EXPENSE = 'REVENUE_EXPENSE';
    case UNREALIZED_INVENTORY_PROFIT = 'UNREALIZED_INVENTORY_PROFIT';
    case FCTR_EQUITY = 'FCTR_EQUITY';
    case INTERCOMPANY_LOAN_BALANCE = 'INTERCOMPANY_LOAN_BALANCE';
    case INTERCOMPANY_INTEREST = 'INTERCOMPANY_INTEREST';
    case ASSET_TRANSFER_INTERCOMPANY = 'ASSET_TRANSFER_INTERCOMPANY';

    public function description(): string
    {
        return match ($this) {
            self::RECIPROCAL_AR_AP => 'Eliminasi Saldo Piutang dan Hutang Timbal Balik',
            self::REVENUE_EXPENSE => 'Eliminasi Penjualan dan Beban Pokok Intra-Grup',
            self::UNREALIZED_INVENTORY_PROFIT => 'Eliminasi Laba Persediaan Belum Terealisasi',
            self::FCTR_EQUITY => 'Cadangan Selisih Kurs Translasi Valas Anak (FCTR)',
            self::INTERCOMPANY_LOAN_BALANCE => 'Eliminasi Saldo Pinjaman Antar-Entitas',
            self::INTERCOMPANY_INTEREST => 'Eliminasi Pendapatan dan Beban Bunga Pinjaman',
            self::ASSET_TRANSFER_INTERCOMPANY => 'Eliminasi Laba Pengalihan Aset Tetap Intra-Grup',
        };
    }
}
