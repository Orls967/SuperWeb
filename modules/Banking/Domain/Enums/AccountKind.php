<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Enums;

enum AccountKind: string
{
    case WALLET = 'wallet';
    case REVENUE = 'revenue';
    case ESCROW = 'escrow';
    case CLEARING = 'clearing';
    case COLLATERAL = 'collateral';
    case EXCHANGE = 'exchange';
    case LOAN_RECEIVABLE = 'loan_receivable';
    case FEE = 'fee';
    case EXPENSE = 'expense';
    case CASH = 'cash';
    case INVENTORY = 'inventory';
    case AP = 'ap';
    case DEPOSIT = 'deposit';
    case LIABILITY = 'liability';
    case ASSET = 'asset';
    case POINTS = 'points';

    public function label(): string
    {
        return match ($this) {
            self::WALLET => 'Dompet Pengguna',
            self::REVENUE => 'Pendapatan',
            self::ESCROW => 'Rekening Bersama (Escrow)',
            self::CLEARING => 'Akun Kliring',
            self::COLLATERAL => 'Kolateral / Jaminan',
            self::EXCHANGE => 'Exchange / Likuiditas',
            self::LOAN_RECEIVABLE => 'Piutang Pinjaman',
            self::FEE => 'Biaya Admin',
            self::EXPENSE => 'Beban Operasional',
            self::CASH => 'Kas Fisik Laci',
            self::INVENTORY => 'Nilai Persediaan',
            self::AP => 'Utang Dagang / Supplier',
            self::DEPOSIT => 'Titipan Deposit',
            self::LIABILITY => 'Kewajiban / Liabilitas',
            self::ASSET => 'Aset',
            self::POINTS => 'Poin Loyalitas',
        };
    }
}
