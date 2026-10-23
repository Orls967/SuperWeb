<?php

declare(strict_types=1);

namespace Modules\Vending\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum VendingTransactionTypes: string implements TransactionTypeRegistry
{
    case VENDING_SALE = 'VENDING_SALE';
}
