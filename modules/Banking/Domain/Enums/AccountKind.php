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
    case CONTRA_ASSET = 'contra_asset';
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
            self::CONTRA_ASSET => 'Kontra Aset (Akumulasi)',
            self::POINTS => 'Poin Loyalitas',
        };
    }

    /**
     * Sisi normal buku besar sesuai konvensi tanda tunggal (KONSEP.md §A2.1).
     * Debit = − (saldo normal <= 0), Kredit = + (saldo normal >= 0).
     *
     * @return 'debit'|'credit'|'free'
     */
    public function normalSide(): string
    {
        return match ($this) {
            self::ASSET,
            self::CASH,
            self::INVENTORY,
            self::LOAN_RECEIVABLE,
            self::EXPENSE => 'debit',

            self::REVENUE,
            self::FEE,
            self::LIABILITY,
            self::AP,
            self::DEPOSIT,
            self::ESCROW,
            self::WALLET,
            self::POINTS,
            self::CONTRA_ASSET,
            self::COLLATERAL => 'credit',

            self::CLEARING,
            self::EXCHANGE => 'free',
        };
    }

    public function isDebitNormal(): bool
    {
        return $this->normalSide() === 'debit';
    }

    public function isCreditNormal(): bool
    {
        return $this->normalSide() === 'credit';
    }

    public function isFree(): bool
    {
        return $this->normalSide() === 'free';
    }
}
