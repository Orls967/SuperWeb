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
    case PRODUCTION = 'production';
    case WASTE = 'waste';
    case CASH_VARIANCE = 'cash_variance';
    case SETTLEMENT = 'settlement';
    case SUPPLIER_PAYABLE = 'supplier_payable';
    case SUPPLIER_PAYMENT = 'supplier_payment';
    case ROYALTY = 'royalty';
    case LEASE_DEPOSIT = 'lease_deposit';
    case LEASE_BILLING = 'lease_billing';
    case PARKING = 'parking';
    case LOYALTY_EARN = 'loyalty_earn';
    case LOYALTY_REDEEM = 'loyalty_redeem';
    case LOYALTY_EXPIRE = 'loyalty_expire';
    case VOUCHER_ISSUE = 'voucher_issue';
    case VOUCHER_SETTLEMENT = 'voucher_settlement';
    case VOUCHER_BREAKAGE = 'voucher_breakage';
    case EVENT_BOOKING = 'event_booking';

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
            self::PRODUCTION => 'Produksi Masakan',
            self::WASTE => 'Pembuangan Makanan / Waste',
            self::CASH_VARIANCE => 'Selisih Kas Shift',
            self::SETTLEMENT => 'Penyelesaian Kas / Kliring',
            self::SUPPLIER_PAYABLE => 'Penerimaan Bahan Supplier',
            self::SUPPLIER_PAYMENT => 'Pembayaran Tagihan Supplier',
            self::ROYALTY => 'Royalti Franchise',
            self::LEASE_DEPOSIT => 'Deposit Sewa Tenant',
            self::LEASE_BILLING => 'Tagihan Sewa Mall',
            self::PARKING => 'Pembayaran Parkir',
            self::LOYALTY_EARN => 'Perolehan Poin Loyalitas',
            self::LOYALTY_REDEEM => 'Penukaran Poin Loyalitas',
            self::LOYALTY_EXPIRE => 'Kedaluwarsa Poin Loyalitas',
            self::VOUCHER_ISSUE => 'Penerbitan Voucher Mall',
            self::VOUCHER_SETTLEMENT => 'Settlement Voucher Tenant',
            self::VOUCHER_BREAKAGE => 'Breakage Voucher Kedaluwarsa',
            self::EVENT_BOOKING => 'Sewa Atrium & Event',
        };
    }
}
