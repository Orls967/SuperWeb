<?php

declare(strict_types=1);

namespace Modules\B2b\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum B2bTransactionTypes: string implements TransactionTypeRegistry
{
    case B2B_ESCROW_HOLD = 'B2B_ESCROW_HOLD';
    case B2B_ESCROW_RELEASE = 'B2B_ESCROW_RELEASE';
}
