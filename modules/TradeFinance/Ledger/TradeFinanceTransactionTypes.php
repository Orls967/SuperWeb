<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum TradeFinanceTransactionTypes: string implements TransactionTypeRegistry
{
    case CROSSBORDER_ESCROW_DEPOSIT = 'CROSSBORDER_ESCROW_DEPOSIT';
    case CROSSBORDER_ESCROW_RELEASE = 'CROSSBORDER_ESCROW_RELEASE';
    case TF_LC_ISSUED = 'TF_LC_ISSUED';
}
