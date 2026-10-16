<?php

declare(strict_types=1);

namespace Modules\Rwa\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum RwaTransactionTypes: string implements TransactionTypeRegistry
{
    case RWA_DIVIDEND = 'rwa_dividend';
}
