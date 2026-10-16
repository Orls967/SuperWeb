<?php

declare(strict_types=1);

namespace Modules\Treasury\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum TreasuryTransactionTypes: string implements TransactionTypeRegistry
{
    case TREASURY_FX_TRANSFER = 'TREASURY_FX_TRANSFER';
}
