<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Enums;

enum TransactionType: string
{
    case TOPUP = 'topup';
    case TRANSFER = 'transfer';
    case FEE = 'fee';
    case PAYMENT = 'payment';
    case REFUND = 'refund';
    case HOLD = 'hold';
    case RELEASE = 'release';
    case MANUAL_ADJUSTMENT = 'manual_adjustment';
    case EXCHANGE = 'exchange';
    case LOAN_DISBURSEMENT = 'loan_disbursement';
    case LOAN_REPAYMENT = 'loan_repayment';
    case LIQUIDATION = 'liquidation';
    case GENESIS = 'genesis';

    public function label(): string
    {
        return match ($this) {
            self::TOPUP => 'Top Up Saldo',
            self::TRANSFER => 'Transfer Antar Pengguna',
            self::FEE => 'Biaya Admin',
            self::PAYMENT => 'Pembayaran Tagihan',
            self::REFUND => 'Pengembalian Dana (Refund)',
            self::HOLD => 'Penahanan Dana (Escrow Hold)',
            self::RELEASE => 'Pelepasan Dana (Escrow Release)',
            self::MANUAL_ADJUSTMENT => 'Penyesuaian Manual Admin',
            self::EXCHANGE => 'Konversi / Trade Kripto',
            self::LOAN_DISBURSEMENT => 'Pencairan Pinjaman',
            self::LOAN_REPAYMENT => 'Pembayaran Cicilan',
            self::LIQUIDATION => 'Likuidasi Kolateral',
            self::GENESIS => 'Saldo Awal Sistem',
        };
    }
}
