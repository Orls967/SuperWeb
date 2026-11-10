<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Enums;

enum IntercompanyTransactionStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case ARM_LENGTH_VALIDATED = 'arm_length_validated';
    case POSTED_SELLER = 'posted_seller';
    case POSTED_BUYER = 'posted_buyer';
    case RECONCILED = 'reconciled';
    case ELIMINATED = 'eliminated';
    case REVERSED = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf Disiapkan',
            self::SUBMITTED => 'Diajukan Menunggu Review',
            self::ARM_LENGTH_VALIDATED => 'Kewajaran TP Tervalidasi',
            self::POSTED_SELLER => 'Faktur Penjual Dibukukan',
            self::POSTED_BUYER => 'Tagihan Pembeli Dibukukan',
            self::RECONCILED => 'Saldo Timbal Balik Cocok',
            self::ELIMINATED => 'Terkonsolidasi & Dieliminasi',
            self::REVERSED => 'Dibatalkan / Jurnal Dibalik',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::ELIMINATED, self::REVERSED], true);
    }
}
