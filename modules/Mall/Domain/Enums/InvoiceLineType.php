<?php

declare(strict_types=1);

namespace Modules\Mall\Domain\Enums;

enum InvoiceLineType: string
{
    case BASE_RENT = 'base_rent';
    case REVENUE_SHARE_TOPUP = 'revenue_share_topup';
    case SERVICE_CHARGE = 'service_charge';
    case ELECTRICITY = 'electricity';
    case WATER = 'water';
    case AC_OVERTIME = 'ac_overtime';
    case PARKING_VALIDATION = 'parking_validation';
    case PENALTY = 'penalty';

    public function label(): string
    {
        return match ($this) {
            self::BASE_RENT => 'Sewa Pokok (Base Rent)',
            self::REVENUE_SHARE_TOPUP => 'Tambahan Bagi Hasil (Rev Share Top-Up)',
            self::SERVICE_CHARGE => 'Biaya Pengelolaan (Service Charge)',
            self::ELECTRICITY => 'Tagihan Listrik',
            self::WATER => 'Tagihan Air PDAM',
            self::AC_OVERTIME => 'Lembur Pendingin Ruangan (AC Overtime)',
            self::PARKING_VALIDATION => 'Validasi Parkir Pelanggan',
            self::PENALTY => 'Denda Keterlambatan Pembayaran',
        };
    }

    /**
     * Kode akun ledger pendapatan untuk baris tagihan ini.
     */
    public function ledgerAccountCode(): string
    {
        return match ($this) {
            self::BASE_RENT, self::REVENUE_SHARE_TOPUP => 'revenue:mall:rent:IDR',
            self::SERVICE_CHARGE => 'revenue:mall:service_charge:IDR',
            self::ELECTRICITY => 'revenue:mall:utilities:electricity:IDR',
            self::WATER => 'revenue:mall:utilities:water:IDR',
            self::AC_OVERTIME => 'revenue:mall:utilities:ac_overtime:IDR',
            // Tarif parkir yang ditanggung tenant tetap diakui sebagai pendapatan parkir mall
            self::PARKING_VALIDATION => 'revenue:mall:parking:IDR',
            self::PENALTY => 'revenue:mall:penalties:IDR',
        };
    }

    /**
     * Prioritas pelunasan saat pembayaran sebagian (1 = tertinggi / dilunasi pertama).
     * Aturan: denda -> utilitas -> service charge -> sewa
     */
    public function paymentPriority(): int
    {
        return match ($this) {
            self::PENALTY => 1,
            self::AC_OVERTIME => 2,
            self::WATER => 3,
            self::ELECTRICITY => 4,
            self::PARKING_VALIDATION => 5,
            self::SERVICE_CHARGE => 6,
            self::REVENUE_SHARE_TOPUP => 7,
            self::BASE_RENT => 8,
        };
    }
}
