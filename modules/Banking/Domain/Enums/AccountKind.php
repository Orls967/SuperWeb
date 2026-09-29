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
        };
    }
}
