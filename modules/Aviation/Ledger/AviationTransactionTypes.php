<?php

declare(strict_types=1);

namespace Modules\Aviation\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

/**
 * Registry tipe transaksi buku besar modul Aviation (Lini 26).
 * Setiap nilai unik dan maksimal 32 karakter (Aturan A5).
 */
enum AviationTransactionTypes: string implements TransactionTypeRegistry
{
    case AVI_TICKET_SALE = 'AVI_TICKET_SALE';
    case AVI_FLIGHT_REVENUE = 'AVI_FLIGHT_REVENUE';
    case AVI_TICKET_REFUND = 'AVI_TICKET_REFUND';
    case AVI_CARGO_REVENUE = 'AVI_CARGO_REVENUE';
    case AVI_MAINT_EXPENSE = 'AVI_MAINT_EXPENSE';
    case AVI_PASSENGER_CARE = 'AVI_PASSENGER_CARE';
    case AVI_MILES_ISSUANCE = 'AVI_MILES_ISSUANCE';
    case AVI_MILES_BREAKAGE = 'AVI_MILES_BREAKAGE';
    case AVI_CODESHARE_SETTLE = 'AVI_CODESHARE_SETTLE';
    case AVI_INS_RECOVERY = 'AVI_INS_RECOVERY';
}

