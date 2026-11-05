<?php

declare(strict_types=1);

namespace Modules\Forest\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum ForestTransactionTypes: string implements TransactionTypeRegistry
{
    case FOREST_TIMBER_SALE = 'FOREST_TIMBER_SALE';

    public function description(): string
    {
        return match ($this) {
            self::FOREST_TIMBER_SALE => 'Penjualan kayu log dan pengakuan pungutan kehutanan PSDH DR',
        };
    }
}
